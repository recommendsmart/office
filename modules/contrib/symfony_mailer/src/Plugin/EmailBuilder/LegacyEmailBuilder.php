<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Exception\SkipMailException;
use Drupal\symfony_mailer\LegacyMailerHelperInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the Legacy Email Builder plug-in that uses a message array.
 */
class LegacyEmailBuilder extends EmailBuilderBase implements ContainerFactoryPluginInterface {

  /**
   * The legacy mailer helper.
   *
   * @var \Drupal\symfony_mailer\LegacyMailerHelperInterface
   */
  protected $legacyHelper;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\symfony_mailer\LegacyMailerHelperInterface $legacy_helper
   *   The legacy mailer helper.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LegacyMailerHelperInterface $legacy_helper, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->legacyHelper = $legacy_helper;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('symfony_mailer.legacy_helper'),
      $container->get('module_handler'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    return $factory->newTypedEmail($message['module'], $message['key'], $message);
  }

  /**
   * {@inheritdoc}
   */
  public function createParams(EmailInterface $email, array $legacy_message = NULL) {
    assert($legacy_message != NULL);
    $email->setParam('legacy_message', $legacy_message)
      ->setParam('__disable_customize__', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function preRender(EmailInterface $email) {
    $message = $email->getParam('legacy_message');
    $message += [
      'subject' => '',
      'body' => [],
      'headers' => [],
    ];

    // Build the email by invoking hook_mail() on this module.
    $args = [$message['key'], &$message, $message['params']];
    $this->moduleHandler->invoke($message['module'], 'mail', $args);

    // Invoke hook_mail_alter() to allow all modules to alter the resulting
    // email.
    $this->moduleHandler->alter('mail', $message);

    if (!$message['send']) {
      throw new SkipMailException('Send aborted by hook_mail().');
    }

    // Fill the email from the message array.
    $email->setBody($this->legacyHelper->formatBody($message['body']));
    $this->legacyHelper->emailFromArray($email, $message);
  }

}
