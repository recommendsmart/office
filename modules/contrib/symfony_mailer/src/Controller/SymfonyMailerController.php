<?php

namespace Drupal\symfony_mailer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\symfony_mailer\Entity\MailerPolicy;
use Drupal\symfony_mailer\MailerTransportInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Route controller for symfony mailer.
 */
class SymfonyMailerController extends ControllerBase {

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $builderManager;

  /**
   * Constructs the MailerCommands object.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   */
  public function __construct(EmailBuilderManagerInterface $email_builder_manager) {
    $this->builderManager = $email_builder_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.email_builder')
    );
  }

  /**
   * Returns a page about the config import status.
   *
   * @return array
   *   Render array.
   */
  public function importStatus() {
    $build = [
      '#type' => 'table',
      '#header' => [
        'name' => $this->t('Name'),
        'state_name' => $this->t('State'),
        'warning' => $this->t('Warning'),
        'operations' => $this->t('Operations'),
      ],
      '#rows' => $this->builderManager->getImportInfo(),
      '#empty' => $this->t('There is no config to import.'),
    ];

    foreach ($build['#rows'] as $id => &$row) {
      $state = $row['state'];
      unset($row['state']);

      $operations['import'] = [
        'title' => ($state == EmailBuilderManagerInterface::IMPORT_COMPLETE) ? $this->t('Re-import') : $this->t('Import'),
        'url' => Url::fromRoute('symfony_mailer.import.import', ['id' => $id]),
      ];

      if ($state == EmailBuilderManagerInterface::IMPORT_READY) {
        $operations['skip'] = [
          'title' => $this->t('Skip'),
          'url' => Url::fromRoute('symfony_mailer.import.skip', ['id' => $id]),
        ];
      }

      $row['operations']['data'] = [
        '#type' => 'operations',
        '#links' => $operations,
      ];
    }

    return $build;
  }

  /**
   * Imports all config not yet imported.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the import status page.
   */
  public function importAll() {
    $this->builderManager->importAll();
    $this->messenger()->addStatus($this->t('Imported all configuration'));
    return $this->redirect('symfony_mailer.import.status');
  }

  /**
   * Imports config for the specified id.
   *
   * @param string $id
   *   The ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the import status page.
   */
  public function import(string $id) {
    $this->builderManager->import($id);
    $label = $this->builderManager->getDefinition($id)['label'];
    $this->messenger()->addStatus($this->t('Imported configuration for %label.', ['%label' => $label]));
    return $this->redirect('symfony_mailer.import.status');
  }

  /**
   * Skips importing config for the specified id.
   *
   * @param string $id
   *   The ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the import status page.
   */
  public function skip(string $id) {
    $this->builderManager->setImportState($id, EmailBuilderManagerInterface::IMPORT_SKIPPED);
    $label = $this->builderManager->getDefinition($id)['label'];
    $this->messenger()->addStatus($this->t('Skipped importing configuration for %label.', ['%label' => $label]));
    return $this->redirect('symfony_mailer.import.status');
  }

  /**
   * Sets the transport as the default.
   *
   * @param \Drupal\symfony_mailer\MailerTransportInterface $mailer_transport
   *   The mailer transport entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the transport listing page.
   */
  public function setAsDefault(MailerTransportInterface $mailer_transport) {
    $mailer_transport->setAsDefault();
    $this->messenger()->addStatus($this->t('The default transport is now %label.', ['%label' => $mailer_transport->label()]));
    return $this->redirect('entity.mailer_transport.collection');
  }

  /**
   * Creates a policy and redirects to the edit page.
   *
   * @param string $policy_id
   *   The policy ID.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the policy edit page.
   */
  public function createPolicy(string $policy_id, Request $request = NULL) {
    MailerPolicy::create(['id' => $policy_id])->save();
    $options = [];
    $query = $request->query;
    if ($query->has('destination')) {
      $options['query']['destination'] = $query->get('destination');
      $query->remove('destination');
    }
    return $this->redirect('entity.mailer_policy.edit_form', ['mailer_policy' => $policy_id], $options);
  }

}
