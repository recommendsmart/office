<?php

declare(strict_types=1);

namespace Drupal\date_filter\Plugin\views\filter;

/**
 * An improved Timestamp views filter class.
 *
 * Based on Drupal\datetime\Plugin\views\filter\Date, hopefully
 * it'll replace its origin one day.
 *
 * @ingroup views_filter_handlers
 */
final class DateTimestamp extends DateBase {

  /**
   * {@inheritdoc}
   */
  protected function processValue($value, string $value_key = ''): ?string {
    $date = $this->getProcessedDate($value, $value_key);
    if ($date === NULL) {
      return NULL;
    }
    return $date->format('U');
  }

  /**
   * {@inheritdoc}
   */
  protected function getInputTimezone(): string {
    return \date_default_timezone_get();
  }

}
