<?php

namespace Drupal\symfony_mailer;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface;

/**
 * Provides a factory for creating email objects.
 */
class EmailFactory implements EmailFactoryInterface {

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $emailBuilderManager;

  /**
   * The email adjuster manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface
   */
  protected $emailAdjusterManager;

  /**
   * The module handler to invoke the alter hook.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the EmailFactory object.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   * @param \Drupal\symfony_mailer\Processor\EmailAdjusterManagerInterface $email_adjuster_manager
   *   The email adjuster manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(EmailBuilderManagerInterface $email_builder_manager, EmailAdjusterManagerInterface $email_adjuster_manager, ModuleHandlerInterface $module_handler) {
    $this->emailBuilderManager = $email_builder_manager;
    $this->emailAdjusterManager = $email_adjuster_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public function sendTypedEmail(string $module, string $sub_type, ...$params) {
    return $this->newTypedEmail($module, $sub_type, ...$params)->send();
  }

  /**
   * {@inheritdoc}
   */
  public function sendEntityEmail(ConfigEntityInterface $entity, string $sub_type, ...$params) {
    return $this->newEntityEmail($entity, $sub_type, ...$params)->send();
  }

  /**
   * {@inheritdoc}
   */
  public function newTypedEmail(string $module, string $sub_type, ...$params) {
    $email = Email::create(\Drupal::getContainer(), $module, $sub_type);
    return $this->initEmail($email, ...$params);
  }

  /**
   * {@inheritdoc}
   */
  public function newEntityEmail(ConfigEntityInterface $entity, string $sub_type, ...$params) {
    $email = Email::create(\Drupal::getContainer(), $entity->getEntityTypeId(), $sub_type, $entity);
    return $this->initEmail($email, ...$params);
  }

  /**
   * Initializes an email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to initialize.
   * @param mixed $params
   *   Parameters for building this email.
   *
   * @return \Drupal\symfony_mailer\EmailInterface
   *   The email.
   */
  protected function initEmail(EmailInterface $email, ...$params) {
    // Load builders with matching ID.
    foreach ($email->getSuggestions('', '.') as $plugin_id) {
      if ($this->emailBuilderManager->hasDefinition($plugin_id)) {
        /** @var \Drupal\symfony_mailer\Processor\EmailBuilderInterface $builder */
        $builder = $this->emailBuilderManager->createInstance($plugin_id);
        if (empty($created)) {
          $builder->createParams($email, ...$params);
          $created = TRUE;
        }
        $builder->init($email);
      }
    }

    // Apply policy.
    $this->emailAdjusterManager->applyPolicy($email);

    // Apply hooks.
    foreach (EmailInterface::PHASE_NAMES as $phase => $name) {
      if ($phase == EmailInterface::PHASE_INIT) {
        // Call init hooks immediately.
        $this->invokeHooks($email);
      }
      else {
        // Add processor to invoke hooks later.
        $email->addProcessor([$this, 'invokeHooks'], $phase, EmailInterface::DEFAULT_WEIGHT, "hook_mailer_$name");
      }
    }

    $email->initDone();
    return $email;
  }

  /**
   * Invokes hooks for an email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email.
   *
   * @see hook_mailer_PHASE()
   * @see hook_mailer_TYPE_PHASE()
   * @see hook_mailer_TYPE__SUBTYPE_PHASE()
   */
  public function invokeHooks(EmailInterface $email) {
    $name = EmailInterface::PHASE_NAMES[$email->getPhase()];
    $type = $email->getType();
    $sub_type = $email->getSubType();
    $hooks = ["mailer", "mailer_$type", "mailer_{$type}__$sub_type"];

    foreach ($hooks as $hook_variant) {
      $this->moduleHandler->invokeAll("{$hook_variant}_$name", [$email]);
    }
  }

}
