<?php

namespace Drupal\symfony_mailer_test;

/**
 * Tracks sent emails for testing.
 */
interface MailerTestServiceInterface {

  /**
   * The name of the state key used for storing sent emails.
   */
  const STATE_KEY = 'mailer_test.emails';

}
