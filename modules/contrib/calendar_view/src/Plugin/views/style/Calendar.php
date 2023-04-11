<?php

namespace Drupal\calendar_view\Plugin\views\style;

/**
 * Deprecated style plugin.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "calendar",
 *   title = @Translation("Calendar (deprecated)"),
 *   help = @Translation("Deprecated. Will be removed on next major release."),
 *   theme = "views_view_calendar",
 *   display_types = {"normal"}
 * )
 */
class Calendar extends CalendarViewBase {

  /**
   * {@inheritDoc}
   */
  public function buildCalendars(int $selected_timestamp): array {
    return [];
  }

}
