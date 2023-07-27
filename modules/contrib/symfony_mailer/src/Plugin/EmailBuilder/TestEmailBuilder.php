<?php

namespace Drupal\symfony_mailer\Plugin\EmailBuilder;

use Drupal\symfony_mailer\EmailInterface;
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
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param mixed $to
   *   The to addresses, see Address::convert().
   */
  public function createParams(EmailInterface $email, $to = NULL) {
    if ($to) {
      // For back-compatibility, allow $to to be NULL.
      $email->setParam('to', $to);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    if ($to = $email->getParam('to')) {
      $email->setTo($to);
    }
  }

}
