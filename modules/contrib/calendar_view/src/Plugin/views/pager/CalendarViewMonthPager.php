<?php

namespace Drupal\calendar_view\Plugin\views\pager;

/**
 * The plugin to handle full pager.
 *
 * @ingroup views_pager_plugins
 *
 * @ViewsPager(
 *   id = "calendar_month",
 *   title = @Translation("Calendar navigation by month"),
 *   short_title = @Translation("Calendar by month"),
 *   help = @Translation("Create a navigation by month for your Calendar Views."),
 *   display_types = {"calendar"},
 *   theme = "calendar_view_pager"
 * )
 */
class CalendarViewMonthPager extends CalendarViewPagerBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Navigation by month');
  }

}
