<?php

namespace Drupal\calendar_view\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;

/**
 * Custom style plugin to render a calendar.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "calendar_week",
 *   title = @Translation("Calendar by week"),
 *   help = @Translation("Displays rows in a calendar by week."),
 *   theme = "views_view_calendar",
 *   display_types = {"normal"}
 * )
 */
class CalendarViewWeek extends CalendarViewBase {

  /**
   * {@inheritDoc}
   */
  public static function getDefaultOptions() {
    $options = parent::getDefaultOptions();
    $options['work_week'] = 0;
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['work_week'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide weekend'),
      '#default_value' => $this->options['work_week'] ?? 0,
    ];
  }

  /**
   * Render a week calendar as a table.
   */
  public function buildTable($year, $week) {
    $days = $this->getOrderedDays();

    $hide_weekend = ($this->options['work_week'] ?? NULL) == 1;

    $headers = [];
    foreach ($days as $number => $name) {
      $headers[$number] = $name;
    }

    // Hide weekend.
    if ($hide_weekend) {
      unset($headers[0], $headers[6]);
    }

    // Dates for this week.
    $week_start = strtotime($year . 'W' . $week);
    $week_date = new \DateTime();
    $week_date->setTimestamp($week_start);

    $cells = [];
    $counter_date = clone $week_date;

    $weekdays = [
      'sunday',
      'monday',
      'tuesday',
      'wednesday',
      'thursday',
      'friday',
      'saturday',
    ];
    $selected_day = key($days);
    $counter_date->modify($weekdays[$selected_day] . ' this week');

    foreach (array_keys($headers) as $number) {
      $time_now = $counter_date->format('U');
      $counter_date->modify('+1 day');

      // Skip weekend.
      if ($hide_weekend && in_array($number, [0, 6])) {
        continue;
      }

      $cells[$time_now] = $this->getCell($time_now);
      $cells[$time_now]['class'] = ['current-month'];
    }

    // Populate one-line table row.
    $rows[] = ['data' => $cells];

    $build = [
      '#type' => 'table',
      '#caption' => $this->t('Week @week - @month @year', [
        '@week' => $week_date->format('W'),
        '@month' => $week_date->format('F'),
        '@year' => $week_date->format('Y'),
      ]),
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => NULL,
      '#attributes' => [
        'data-calendar-view-year' => $week_date->format('Y'),
        'data-calendar-view-month' => $week_date->format('m'),
        'data-calendar-view-week' => $week_date->format('W'),
        'class' => [
          'calendar-view-table',
          'calendar-view-week',
        ],
      ],
    ];

    if ($hide_weekend) {
      $build['#attributes']['data-calendar-hide-weekend'] = TRUE;
    }

    return $build;
  }

  /**
   * {@inheritDoc}
   */
  public function buildCalendars(int $selected_timestamp): array {
    $year = date('Y', $selected_timestamp);
    $week = date('W', $selected_timestamp);
    $calendars[$year . 'W' . $week] = $this->buildTable($year, $week);
    return $calendars;
  }

  /**
   * {@inheritDoc}
   */
  public function getQueryDatetimeStart(\Datetime $now): \Datetime {
    $start = clone $now;

    $start
      ->modify('-1 week')
      ->modify('last day of this week')
      ->setTime(23, 59, 59);

    return $start;
  }

  /**
   * {@inheritDoc}
   */
  public function getQueryDatetimeEnd(\Datetime $now): \Datetime {
    $end = clone $now;

    $end
      ->modify('+1 week')
      ->modify('first day of this week')
      ->setTime(0, 0, 0);

    return $end;
  }

}
