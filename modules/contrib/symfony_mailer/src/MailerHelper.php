<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface;
use Html2Text\Html2Text;

/**
 * Provides the mailer helper service.
 */
class MailerHelper implements MailerHelperInterface {

  use StringTranslationTrait;

  /**
   * Regular expression for parsing addresses.
   *
   * Matches a string like 'Name <email@address.com>' Anything between the
   * first < and last > counts as the email address. This does not try to cover
   * all edge cases for address.
   */
  protected const FROM_STRING_PATTERN = '~(?<displayName>[^<]*)<(?<addrSpec>.*)>[^>]*~';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The email adjuster manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface
   */
  protected $adjusterManager;

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $builderManager;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs the MailerHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface $email_adjuster_manager
   *   The email adjuster manager.
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EmailAdjusterManagerInterface $email_adjuster_manager, EmailBuilderManagerInterface $email_builder_manager, ConfigFactoryInterface $config_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->adjusterManager = $email_adjuster_manager;
    $this->builderManager = $email_builder_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function parseAddress(string $encoded, string $langcode = NULL) {
    foreach (explode(',', $encoded) as $part) {
      // Code copied from \Symfony\Component\Mime\Address::create().
      if (strpos($part, '<')) {
        if (!preg_match(self::FROM_STRING_PATTERN, $part, $matches)) {
          throw new InvalidArgumentException("Could not parse $part as an address.");
        }
        $addresses[] = new Address($matches['addrSpec'], trim($matches['displayName'], ' \'"'), $langcode);
      }
      else {
        $addresses[] = new Address($part, NULL, $langcode);
      }
    }
    return $addresses ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function policyFromAddresses(array $addresses) {
    $site_mail = $this->configFactory->get('system.site')->get('mail');

    foreach ($addresses as $address) {
      $value = $address->getEmail();
      if ($value == $site_mail) {
        $value = '<site>';
      }
      elseif ($user = $address->getAccount()) {
        $value = $user->id();
      }
      else {
        $display = $address->getDisplayName();
      }

      $config['addresses'][] = [
        'value' => $value,
        'display' => $display ?? '',
      ];
    }

    return $config ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function htmlToText(string $html) {
    // Convert to plain text.
    // - Core uses MailFormatHelper::htmlToText(). However this is old code
    //   that's not actively maintained there's no need for a Drupal-specific
    //   version of this generic code.
    // - Symfony Mailer library uses league/html-to-markdown. This is a bigger
    //   step away from what's been done in Drupal before, so we won't do that.
    // - Swiftmailer uses html2text/html2text, and that's what we do.
    return (new Html2Text($html))->getText();
  }

  /**
   * {@inheritdoc}
   */
  public function config() {
    return $this->configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function renderEntityPolicy(ConfigEntityInterface $entity, string $subtype) {
    $type = $entity->getEntityTypeId();
    $policy_id = "$type.$subtype";
    $entities = [$policy_id];
    if (!$entity->isNew()) {
      $entities[] = $policy_id . '.' . $entity->id();
    }
    $element = $this->renderCommon($type);
    $element['listing'] = $this->entityTypeManager->getListBuilder('mailer_policy')
      ->overrideEntities($entities)
      ->hideColumns(['type', 'sub_type'])
      ->render();

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function renderTypePolicy(string $type) {
    $element = $this->renderCommon($type);
    $entities = [$type];
    foreach (array_keys($this->builderManager->getDefinition($type)['sub_types']) as $subtype) {
      $entities[] = "$type.$subtype";
    }

    $element['listing'] = $this->entityTypeManager->getListBuilder('mailer_policy')
      ->overrideEntities($entities)
      ->hideColumns(['type', 'entity'])
      ->render();

    return $element;
  }

  /**
   * Renders common parts for policy elements.
   *
   * @param string $type
   *   Type of the policies to show.
   *
   * @return array
   *   The render array.
   */
  protected function renderCommon(string $type) {
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Mailer policy'),
      '#collapsible' => FALSE,
      '#description' => $this->t('If you have made changes on this page, please save them before editing policy.'),
    ];

    $definition = $this->builderManager->getDefinition($type);
    $element['explanation'] = [
      '#prefix' => '<p>',
      '#markup' => $this->t('Configure Mailer policy records to customise the emails sent for @label.', ['@label' => $definition['label']]),
      '#suffix' => '</p>',
    ];

    foreach ($definition['common_adjusters'] as $adjuster_id) {
      $adjuster_names[] = $this->adjusterManager->getDefinition($adjuster_id)['label'];
    }

    if (!empty($adjuster_names)) {
      $element['explanation']['#markup'] .= ' ' . $this->t('You can set the @adjusters and more.', ['@adjusters' => implode(', ', $adjuster_names)]);
    }

    return $element;
  }

}
