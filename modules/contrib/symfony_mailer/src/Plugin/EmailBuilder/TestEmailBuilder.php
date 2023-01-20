<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailFactoryInterface;
use Drupal\symfony_mailer\Processor\EmailBuilderBase;
use Drupal\symfony_mailer\Processor\TokenProcessorTrait;

/**
 * Defines the Email Builder plug-in for test mails.
 *
 * @EmailBuilder(
 *   id = "symfony_mailer",
 *   sub_types = { "test" = @Translation("Test email") },
 *   common_adjusters = {"email_subject", "email_body"},
 * )
 */
class TestEmailBuilder extends EmailBuilderBase {

  use TokenProcessorTrait;

  /**
   * {@inheritdoc}
   */
  public function fromArray(EmailFactoryInterface $factory, array $message) {
    return $factory->newTypedEmail($message['module'], $message['key']);
  }

}
