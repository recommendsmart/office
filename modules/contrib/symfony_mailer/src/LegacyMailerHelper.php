<?php

namespace Drupal\symfony_mailer;

use Drupal\Component\Render\MarkupInterface;

/**
 * Provides the legacy mailer helper service.
 */
class LegacyMailerHelper implements LegacyMailerHelperInterface {

  /**
   * List of headers for conversion to array.
   *
   * @var array
   */
  protected const HEADERS = [
    'From' => 'from',
    'Reply-To' => 'reply-to',
    'To' => 'to',
    'Cc' => 'cc',
    'Bcc' => 'bcc',
  ];

  /**
   * The mailer helper.
   *
   * @var \Drupal\symfony_mailer\MailerHelperInterface
   */
  protected $mailerHelper;

  /**
   * Constructs the MailerHelper object.
   *
   * @param \Drupal\symfony_mailer\MailerHelperInterface $mailer_helper
   *   The mailer helper.
   */
  public function __construct(MailerHelperInterface $mailer_helper) {
    $this->mailerHelper = $mailer_helper;
  }

  /**
   * {@inheritdoc}
   */
  public function formatBody(array $body_array) {
    foreach ($body_array as $part) {
      if ($part instanceof MarkupInterface) {
        $body[] = ['#markup' => $part];
      }
      else {
        $body[] = [
          '#type' => 'processed_text',
          '#text' => $part,
        ];
      }
    }
    return $body ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function emailToArray(EmailInterface $email, array &$message) {
    $message['subject'] = $email->getSubject();
    if ($email->getPhase() >= EmailInterface::PHASE_POST_RENDER) {
      $message['body'] = $email->getHtmlBody();
    }

    $headers = $email->getHeaders();
    foreach (self::HEADERS as $name => $key) {
      if ($headers->has($name)) {
        $message['headers'][$name] = $headers->get($name)->getBodyAsString();
      }
      if ($key) {
        $message[$key] = $message['headers'][$name] ?? NULL;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function emailFromArray(EmailInterface $email, array $message) {
    $email->setSubject($message['subject']);

    // Attachments.
    $attachments = $message['params']['attachments'] ?? [];
    foreach ($attachments as $attachment) {
      $email->attachFromPath($attachment['filepath'], $attachment['filename'] ?? NULL, $attachment['filemime'] ?? NULL);
    }

    // Address headers.
    foreach (self::HEADERS as $name => $key) {
      $encoded = $message['headers'][$name] ?? $message[$key] ?? NULL;
      if (isset($encoded)) {
        $email->setAddress($name, $this->mailerHelper->parseAddress($encoded));
      }
    }
  }

}
