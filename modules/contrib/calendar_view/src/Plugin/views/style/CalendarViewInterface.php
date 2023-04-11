<?php

namespace Drupal\calendar_view\Plugin\views\style;

use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\ResultRow;

/**
 * Defines required methods class for Calendar View style plugin.
 */
interface CalendarViewInterface {

  /**
   * Retrieve the calendar date.
   *
   * @return string
   *   A UNIX timestamp.
   */
  public function getCalendarTimestamp(): string;

  /**
   * Where the magic happens.
   *
   * @param \Drupal\views\ResultRow $result
   *   A given view result.
   * @param \Drupal\views\Plugin\views\field\EntityField $field
   *   A given date field.
   *
   * @return array
   *   The list of calendar rows, keyed by the timestamp of the day.
   */
  public function processResult(ResultRow $result, EntityField $field): array;

  /**
   * Build the list of calendars.
   *
   * @param int $selected_timestamp
   *   The calendar timestamp.
   *
   * @return array
   *   A list of renderable arrays.
   */
  public function buildCalendars(int $selected_timestamp): array;

}
