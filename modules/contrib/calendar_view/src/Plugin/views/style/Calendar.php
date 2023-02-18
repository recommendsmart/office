<?php

namespace Drupal\calendar_view\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\style\DefaultStyle;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Custom style plugin to render a calendar.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "calendar",
 *   title = @Translation("Calendar"),
 *   help = @Translation("Displays rows in a calendar by month."),
 *   theme = "views_view_calendar",
 *   display_types = {"normal"}
 * )
 */
class Calendar extends DefaultStyle {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->logger = $container->get('logger.channel.calendar_view');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    return $instance;
  }

  /**
   * Check if a field is supported by this plugin.
   *
   * @param mixed $field
   *   A given View field.
   *
   * @return bool
   *   Wether or not the field is supported in Calendar View.
   */
  public function isDateField($field) {
    $definition = NULL;
    $field_storages = [];

    if ($field instanceof EntityField) {
      $entity_type_id = $field->configuration['entity_type'] ?? NULL;
      $field_name = $field->configuration['entity field'] ??
        $field->configuration['field_name'] ?? NULL;

      // Improve performance with static variables.
      $field_storages = &drupal_static(__FUNCTION__);
      if (!isset($field_storages) || !($field_storages[$entity_type_id] ?? NULL)) {
        $field_storages[$entity_type_id] = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
      }

      $definition = $field_storages[$entity_type_id][$field_name] ?? NULL;
    }

    return !$definition ? FALSE : in_array($definition->getType(), [
      'created', 'changed', 'datetime', 'daterange', 'smartdate',
    ]);
  }

  /**
   * A scientific methods to get the list of days of the week.
   *
   * @return array
   *   The list of days, keyed by their number.
   */
  public function getOrderedDays() {
    $days = [
      0 => t('Sunday'),
      1 => t('Monday'),
      2 => t('Tuesday'),
      3 => t('Wednesday'),
      4 => t('Thursday'),
      5 => t('Friday'),
      6 => t('Saturday'),
    ];

    $weekday_start = $this->options['calendar_weekday_start'] ?? 0;
    $weekdays = range($weekday_start, 6);
    $days = array_replace(array_flip($weekdays), $days);

    return $days;
  }

  /**
   * Retrieve all fields.
   *
   * @return array
   *   List of field, keyed by field ID.
   */
  public function getFields() {
    // Improve performance with static variables.
    $view_fields = &drupal_static(__FUNCTION__);
    if (isset($view_fields)) {
      return $view_fields;
    }

    $view_fields = $this->view->display_handler->getHandlers('field') ?? [];
    return $view_fields;
  }

  /**
   * Retrieve all Date fields.
   *
   * @return array
   *   List of View field plugin, keyed by their name.
   */
  public function getDateFields() {
    // Improve performance with static variables.
    $date_fields = &drupal_static(__FUNCTION__);
    if (isset($date_fields)) {
      return $date_fields;
    }

    $date_fields = array_filter($this->view->display_handler->getHandlers('field'), function ($field) {
      return $this->isDateField($field);
    });

    return $date_fields;
  }

  /**
   * Retrieve the calendar date.
   *
   * @return string
   *   A UNIX timestamp.
   */
  public function getCalendarTimestamp() {
    // Avoid unnecessary calls with static variable.
    $timestamp = &drupal_static(__FUNCTION__);
    if (isset($timestamp)) {
      return $timestamp;
    }

    // Allow user to pass query string.
    // (i.e "<url>?calendar_timestamp=2022-12-31").
    $selected_timestamp = $this->view->getExposedInput()['calendar_timestamp'] ?? NULL;
    $timestamp = _calendar_view_convert_to_timestamp($selected_timestamp);

    // Get date (default: today).
    $default_timestamp = !empty($this->options['calendar_timestamp']) ? $this->options['calendar_timestamp'] : NULL;
    if ($default_timestamp && !ctype_digit(strval($default_timestamp))) {
      $default_timestamp = strtotime($default_timestamp);
    }

    // Get first result's timestamp.
    $first_timestamp = NULL;
    if (empty($this->options['calendar_timestamp'])) {
      if ($first_result = $this->view->result[0] ?? NULL) {
        $available_date_fields = $this->getDateFields();
        $rows = $this->processResult($first_result, reset($available_date_fields));
        $timestamps = array_keys($rows);
        $first_timestamp = reset($timestamps) ?? NULL;
      }
    }

    $timestamp = $selected_timestamp ?? $default_timestamp ?? $first_timestamp ?? date('U');

    return _calendar_view_convert_to_timestamp($timestamp);
  }

  /**
   * Get default options, statically.
   *
   * @return array
   *   The value list.
   */
  public static function getDefaultOptions() {
    return [
      'calendar_fields' => [],
      'calendar_display_rows' => 0,
      // Start on Monday by default.
      'calendar_weekday_start' => 1,
      'calendar_sort_order' => 'ASC',
      'calendar_timestamp' => 'this month',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $defaults = self::getDefaultOptions();
    foreach ($defaults as $key => $value) {
      $options[$key] = ['default' => $value];
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['calendar_display_rows'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display default View results'),
      '#description' => $this->t('If selected, View results rows are also display along the calendar.'),
      '#default_value' => $this->options['calendar_display_rows'] ?? 0,
    ];

    $date_fields = $this->getDateFields();
    $date_fields_keys = array_keys($date_fields);
    $default_date_field = [reset($date_fields_keys)];

    $form['calendar_fields'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Date fields'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => array_combine($date_fields_keys, $date_fields_keys),
      '#default_value' => $this->options['calendar_fields'] ?? $default_date_field,
      '#disabled' => empty($date_fields),
    ];
    if (empty($date_fields)) {
      $form['calendar_fields']['#description'] = $this->t('Add a date field in <em>fields</em> on this View and edit this setting again to activate the Calendar.');
    }

    $form['calendar_weekday_start'] = [
      '#type' => 'select',
      '#title' => $this->t('Start week on:'),
      '#options' => [
        1 => t('Monday'),
        2 => t('Tuesday'),
        3 => t('Wednesday'),
        4 => t('Thursday'),
        5 => t('Friday'),
        6 => t('Saturday'),
        0 => t('Sunday'),
      ],
      '#default_value' => $this->options['calendar_weekday_start'] ?? 1,
    ];

    $form['calendar_sort_order'] = [
      '#type' => 'select',
      '#title' => $this->t('Default sort order'),
      '#options' => [
        'ASC' => $this->t('Chronological'),
        'DESC' => $this->t('Antichronological'),
      ],
      '#description' => $this->t('Sort results ASC or DESC inside a day.'),
      '#default_value' => $this->options['calendar_sort_order'] ?? 'ASC',
      '#required' => TRUE,
    ];

    $form['calendar_timestamp'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default date'),
      '#description' => $this->t('Default starting date of this calendar, in any machine readable format.') . '<br>' .
      $this->t('Leave empty to use the date of the first result out of the first selected Date filter above.') . '<br>' .
      $this->t('NB: The first result is controlled by the <em>@sort_order</em> on this View.', [
        '@sort_order' => $this->t('Sort order'),
      ]),
      '#default_value' => $this->options['calendar_timestamp'] ?? 'this month',
    ];
  }

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
  public function processResult(ResultRow $result, EntityField $field) {
    $rows = [];
    $values = [];
    $config = $field->configuration ?? [];
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $field->getEntity($result);
    $field_id = $config['field_name'] ?? $config['entity field'] ?? $config['id'] ?? NULL;

    // By default, get field value from EntityField View plugin.
    // Wrap it in a list for looping later to be consistent with other multi
    // value date field types.
    $field_values = [$field->getValue($result, $field_id)];

    // If possble, get the real field value (e.g. start/end).
    if ($entity && $field_id && $entity->hasField($field_id)) {
      $items = $entity->get($field_id);
      $field_values = $items->getValue();
    }

    foreach ($field_values as $delta => $value) {
      $values[$delta] = [];
      $values[$delta]['entity'] = $entity;
      $values[$delta]['calendar_field'] = $field;

      // Get a unique identifier for this event.
      $result_hash = md5(serialize($result) . $field_id . $delta);
      $values[$delta]['hash'] = $result_hash;

      // Get event timestamp(s).
      $from = $values[$delta]['from'] = _calendar_view_convert_to_timestamp($value['value'] ?? $value);
      $to = $values[$delta]['to'] = _calendar_view_convert_to_timestamp($value['end_value'] ?? NULL);

      // Calculate days span.
      $values[$delta]['instance'] = 0;
      $values[$delta]['instances'] = 0;
      if ($to && $to > $from) {
        $date_time_from = new \DateTime();
        $date_time_from->setTimestamp($from);
        $date_time_to = new \DateTime();
        $date_time_to->setTimestamp($to);
        $interval = $date_time_to->diff($date_time_from);
        $values[$delta]['instances'] = $interval->d;
      }

      // Insert first event in calendar content.
      if ($from) {
        // Content is keyed by start of day (i.e. timestamp at 00:00:00).
        $date_time_from = new \DateTime();
        $date_time_from->setTimestamp($from);
        $date_time_from->modify('midnight');
        $timestamp_from = $date_time_from->getTimestamp();

        // Know about the first event for reordering in cell.
        $values[$delta]['parent'] = $timestamp_from;

        // Keep track of thing for later use.
        // @see template_preprocess_calendar_view_day()
        $result->calendar_view[$field_id][$timestamp_from] = $values[$delta];

        $rows[$timestamp_from][] = $result;
      }

      // Insert all other events in calendar content.
      for ($i = $values[$delta]['instances']; $i > 0; $i--) {
        $date_time_from->modify('+1 day');
        $date_time_from->modify('midnight');
        $timestamp_to = $date_time_from->getTimestamp();

        // Next event in the series.
        $values[$delta]['instance'] = ($interval->d - ($i - 1));

        // Create a duplicate result.
        // Keep track of thing for later use.
        // @see template_preprocess_calendar_view_day()
        $cloned_result = clone $result;
        $cloned_result->calendar_view[$field_id][$timestamp_to] = $values[$delta];

        $rows[$timestamp_to][] = $cloned_result;
        unset($cloned_result);
      }
    }

    return $rows;
  }

  /**
   * {@inheritDoc}
   */
  public function preRender($results) {
    parent::preRender($results);

    // Build calendar by fields.
    $available_date_fields = $this->getDateFields();
    $calendar_fields = $this->options['calendar_fields'] ?? [];
    $calendar_fields = array_filter($calendar_fields, function ($field_name) use ($available_date_fields) {
      return ($field_name !== 0) && isset($available_date_fields[$field_name]);
    });

    $list = [];
    foreach ($calendar_fields as $field_id) {
      foreach ($results as &$result) {
        $field = $available_date_fields[$field_id];
        if (!$field instanceof EntityField) {
          continue;
        }

        // Prepare calendar rows.
        $rows = $this->processResult($result, $field);
        foreach ($rows as $timestamp => $processed_rows) {
          foreach ($processed_rows as $row) {
            $list[$field_id][$timestamp][] = $row;
          }
        }
      }

      if (!empty($list[$field_id] ?? [])) {
        // Sort results in each cell.
        $sort_order = $this->options['calendar_sort_order'] ?? 'ASC';
        if ($sort_order == 'DESC') {
          krsort($list[$field_id]);
        }
        else {
          ksort($list[$field_id]);
        }

        // Make sure timestamps are valid.
        $timestamps = array_filter(array_keys($list[$field_id]), function ($value) {
          return _calendar_view_convert_to_timestamp(($value));
        });
      }
    }

    // Remove results if out of calendar limits.
    $selected_timestamp = $this->getCalendarTimestamp();
    $date_time_selected = new \DateTime();
    $date_time_selected->setTimestamp($selected_timestamp);
    $selected_month_start = strtotime($date_time_selected->format('Y-m-01'));
    $selected_month_end = strtotime($date_time_selected->format('Y-m-t'));

    $timestamps = array_filter(array_keys($list), function ($value) use ($selected_month_start, $selected_month_end) {
      return $value >= $selected_month_start && $value <= $selected_month_end;
    });

    // Render an empty calendar if no results.
    $timestamps = !empty($timestamps) ? $timestamps : [$selected_month_start];

    // Build calendars.
    $calendars = [];
    $year = date('Y', $selected_timestamp);
    $month = date('m', $selected_timestamp);

    // @todo Implements more Calendar types as plugins (i.e. month, week, day).
    $calendar_type = $this->configuration['calendar_type'] ?? 'month';
    switch ($calendar_type) {
      case 'month':
        if (!isset($calendars[$year . $month])) {
          $calendars[$year . $month] = $this->buildTable($year, $month);
        }

        // Populate calendar cells.
        foreach ($calendars as $i => $table) {
          foreach (($table['#rows'] ?? []) as $delta => $rows) {
            foreach (array_keys($rows['data'] ?? []) as $timestamp) {
              foreach (array_keys($list) as $field_id) {
                foreach (($list[$field_id][$timestamp] ?? []) as $result) {
                  // Render row and keep track of values for later use.
                  // @see template_preprocess_calendar_view_day()
                  $renderable_row = $this->view->rowPlugin->render($result);
                  $renderable_row['#calendar_view'] = $result->calendar_view[$field_id][$timestamp] ?? [];
                  $renderable_row['#timestamp'] = $timestamp;
                  $renderable_row['#view'] = $this->view;

                  // Insert content in cell.
                  $cell = &$calendars[$i]['#rows'][$delta]['data'][$timestamp];
                  $cell['data']['#children'][] = $renderable_row;
                }
              }
            }
          }
        }
        break;
    }

    $this->view->calendars = $calendars;
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    // Add default cache tags to Calendars.
    $cache_tags = $this->view->getCacheTags() ?? [];
    foreach ($this->view->calendars ?? [] as &$calendar) {
      $calendar['#cache']['contexts'] = ['url.query_args:calendar_timestamp'];
      $calendar['#cache']['tags'] = $cache_tags;
    }

    return parent::render();
  }

  /**
   * Render array for a table cell.
   *
   * @param int $timestamp
   *   A given UNIX timestamp.
   * @param array $children
   *   A given list of children elements.
   *
   * @return array
   *   A cell content, as a render array.
   */
  public function getCell(int $timestamp, array $children = []) {
    $cell = [];
    $cell['data'] = [
      '#theme' => 'calendar_view_day',
      '#timestamp' => $timestamp,
      '#children' => $children,
      '#options' => $this->options,
      '#view' => $this->view,
    ];

    $cell['data-calendar-view-day'] = date('d', $timestamp);
    $cell['data-calendar-view-month'] = date('m', $timestamp);
    $cell['data-calendar-view-year'] = date('y', $timestamp);

    if (date('U', $timestamp) == strtotime('today')) {
      $cell['data-calendar-today'] = TRUE;
    }

    return $cell;
  }

  /**
   * Render a month calendar as a table.
   */
  public function buildTable($year, $month) {
    $days = $this->getOrderedDays();

    $headers = [];
    foreach ($days as $number => $name) {
      $headers[$number] = $name;
    }

    // Dates for this month.
    $month_start = strtotime("$year-$month-01");
    $month_days = date('t', $month_start);
    $first_day = date('w', $month_start);
    $month_weekday_start = array_search($first_day, array_keys($headers));
    $month_weeks = ceil(($month_weekday_start + $month_days) / 7);

    // Next month.
    $next_month = $month == '12' ? '01' : str_pad(($month + 1), 2, '0', STR_PAD_LEFT);
    $next_year = $month == '12' ? $year + 1 : $year;

    // Last month.
    $previous_month = $month == '01' ? '12' : str_pad(($month - 1), 2, '0', STR_PAD_LEFT);
    $previous_year = $month == '01' ? $year - 1 : $year;
    $previous_month_start = strtotime($previous_year . '-' . $previous_month . '-' . '01');
    $previous_month_days = date('t', $previous_month_start);

    $previous_month_offset = [];
    foreach (array_keys($headers) as $number) {
      // Check if month started.
      if ((int) $number == (int) $first_day) {
        break;
      }
      $previous_month_offset[] = $previous_month_days;
      $previous_month_days--;
    }
    $previous_month_offset = array_reverse($previous_month_offset);

    $count = 0;
    for ($i = 0; $i < $month_weeks; $i++) {
      // Prepare row.
      $cells = [];

      // First week.
      if ($i == 0) {
        // Empty days starting the month display.
        foreach ($previous_month_offset as $daynum) {
          $day_number = str_pad($daynum, 2, '0', STR_PAD_LEFT);
          $time_now = strtotime($previous_year . '-' . $previous_month . '-' . $day_number);

          $cells[$time_now] = $this->getCell($time_now);
          $cells[$time_now]['class'] = ['previous-month'];
        }

        // Pending days of this month's first week.
        $x = 7 - count($previous_month_offset);
        do {
          $x--;

          // Count days of the month.
          $count++;

          // Get this day's timestamp.
          $day_number = str_pad($count, 2, '0', STR_PAD_LEFT);
          $time_now = strtotime($year . '-' . $month . '-' . $day_number);

          $cells[$time_now] = $this->getCell($time_now);
          $cells[$time_now]['class'] = ['current-month'];
        } while ($x >= 1);

        // Populate table row.
        $rows[] = ['data' => $cells];

        continue;
      }

      // Rest of the weeks.
      $daynum = 0;
      foreach (array_keys($headers) as $number) {
        // Count days of the month.
        $count++;

        // Fill next months day, if necessary.
        $month_finished = $count > (int) $month_days;
        $week_finished = $number == count($headers);
        if ($month_finished && !$week_finished) {
          $daynum++;
          $time_now = strtotime($next_year . '-' . $next_month . '-' . $daynum);

          $cells[$time_now] = $this->getCell($time_now);
          $cells[$time_now]['class'] = ['next-month'];
          continue;
        }

        // Stop now.
        if ($month_finished) {
          break;
        }

        // Insert day.
        $day_number = str_pad($count, 2, '0', STR_PAD_LEFT);
        $time_now = strtotime($year . '-' . $month . '-' . $day_number);

        $cells[$time_now] = $this->getCell($time_now);
        $cells[$time_now]['class'] = ['current-month'];
      }

      // Populate table row.
      $rows[] = ['data' => $cells];
    }

    $caption = $this->dateFormatter->format($month_start, 'custom', 'F Y');

    $build = [
      '#type' => 'table',
      '#caption' => $caption,
      '#header' => $headers,
      '#rows' => $rows,
      '#empty' => NULL,
      '#attributes' => [
        'summary' => $this->view->getTitle(),
        'data-calendar-view-year' => $year,
        'data-calendar-view-month' => $month,
        'class' => [
          'calendar-view-table',
          'calendar-view-month',
        ],
      ],
    ];

    return $build;
  }

}
