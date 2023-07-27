<?php

namespace Drupal\calendar_view\Plugin\views\pager;

/**
 * The plugin to handle full pager.
 *
 * @ingroup views_pager_plugins
 *
 * @ViewsPager(
 *   id = "calendar_week",
 *   title = @Translation("Calendar navigation by week"),
 *   short_title = @Translation("Calendar by week"),
 *   help = @Translation("Create a navigation by week for your Calendar Views."),
 *   display_types = {"calendar"},
 *   theme = "calendar_view_pager"
 * )
 */
class CalendarViewWeekPager extends CalendarViewPagerBase {

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Navigation by week');
  }

  /**
   * {@inheritDoc}
   */
  public function getDatetimePrevious(\Datetime $now): \Datetime {
    $date = clone $now;
    $date->modify('-7 days');
    $date->setTime(0, 0, 0);
    return $date;
  }

  /**
   * {@inheritDoc}
   */
  public function getDatetimeNext(\Datetime $now): \Datetime {
    $date = clone $now;
    $date->modify('+7 days');
    $date->setTime(0, 0, 0);
    return $date;
  }

}
