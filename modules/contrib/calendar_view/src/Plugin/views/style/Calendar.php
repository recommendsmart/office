<?php

namespace Drupal\calendar_view\Plugin\views\style;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\filter\Date as DateFilter;
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
 *   help = @Translation("Displays rows in a calendar."),
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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->logger = $container->get('logger.channel.calendar_view');
    return $instance;
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
   * Retrieve all Date fields exposed as filters.
   *
   * @return array
   *   List of exposed filter names, keyed by field ID.
   */
  public function getDateFilters() {
    // Improve performance with static variables.
    $date_filters = &drupal_static(__FUNCTION__);
    if (isset($date_filters)) {
      return $date_filters;
    }

    $date_filters = [];
    $filters = $this->view->display_handler->getHandlers('filter');
    foreach ($filters as $filter_id => $filter) {
      $date_filters[$filter_id] = $filter instanceof DateFilter ? $filter : NULL;
    }
    return array_filter($date_filters);
  }

  /**
   * Retrieve the calendar date.
   *
   * @return string
   *   A UNIX timestamp.
   */
  public function getCalendarTimestamp() {
    // Avoid unnecessary calls with static variable.
    $default_time = &drupal_static(__FUNCTION__);
    if (isset($default_time)) {
      return $default_time;
    }

    // Get date (default: today).
    $timestamp = $this->view->getExposedInput()['calendar_timestamp'] ?? NULL;
    $timestamp = empty($timestamp) ? date('U') : $timestamp;

    // Allow user to pass query string.
    // (i.e "<url>?calendar_timestamp=2022-12-31").
    if (!ctype_digit(strval($timestamp))) {
      $timestamp = strtotime($timestamp);
    }

    return $timestamp;
  }

  /**
   * Get a field related to a filter from a given View.
   *
   * @param \Drupal\views\Plugin\views\filter\Date $filter
   *   A given date filter.
   *
   * @return \Drupal\views\Plugin\views\field\FieldPluginBase|null
   *   The view field or nothing.
   */
  public function getFilterFieldname(DateFilter $filter) {
    $fields = $this->getFields() ?? [];

    $field_name = $filter->configuration['field_name'] ??
      $filter->configuration['entity field'] ??
      $filter->configuration['id'] ??
      $filter->getPluginId();

    $field = $fields[$field_name] ?? NULL;

    return $field && isset($field->field) ? $field->field : $field_name;
  }

  /**
   * Get entity from a View result row by a given filter.
   *
   * @param \Drupal\views\ResultRow $result
   *   A given View result.
   * @param string $field_name
   *   A given field name.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   The entity or nothing.
   */
  public function getEntityByField(ResultRow $result, $field_name) {
    $fields = $this->getFields() ?? [];
    if ($field = $fields[$field_name] ?? NULL) {
      return $field->getEntity($result);
    }

    if ($result instanceof ResultRow && isset($result->_entity)) {
      return $result->_entity;
    }

    return NULL;
  }

  /**
   * Get default options, statically.
   *
   * @return array
   *   The value list.
   */
  public static function getDefaultOptions() {
    return [
      'calendar_filters' => [],
      'calendar_display_rows' => 0,
      // Start on Monday by default.
      'calendar_weekday_start' => 1,
      'calendar_sort_order' => 'ASC',
      'calendar_timestamp' => 'this month',
      'calendar_display' => 'month',
      'calendar_display_hours' => 1,
      'calendar_day_hours_start' => 8,
      'calendar_day_hours_end' => 18,
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

    $date_filters = $this->getDateFilters();
    $date_filter_keys = array_keys($date_filters);
    $default_date_filter = [reset($date_filter_keys)];

    $form['calendar_filters'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Date fields'),
      '#empty_option' => $this->t('- Select -'),
      '#options' => array_combine($date_filter_keys, $date_filter_keys),
      '#default_value' => $this->options['calendar_filters'] ?? $default_date_filter,
      '#disabled' => empty($date_filters),
    ];
    if (empty($date_filters)) {
      $form['calendar_filters']['#description'] = $this->t('Add a date field in <em>filters</em> on this View to activate the Calendar.');
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
      '#description' => $this->t('The default date of this calendar, in any machine readable format.'),
      '#default_value' => $this->options['calendar_timestamp'] ?? 'this month',
    ];

    $hours = [];
    for ($i = 0; $i <= 24; $i++) {
      $v = str_pad($i, 2, '0', STR_PAD_LEFT);
      $v = str_pad($v, 4, '0', STR_PAD_RIGHT);
      $h = substr($v, 0, 2);
      $m = substr($v, 2, 2);
      $hours[$i] = $h . ':' . $m;
    }

    $form['calendar_display'] = [
      '#type' => 'select',
      '#title' => $this->t('Display type:'),
      '#options' => [
        'month' => $this->t('Month'),
        'week' => $this->t('Week (WIP)'),
        'day' => $this->t('Day (WIP)'),
        'agenda' => $this->t('Agenda (WIP)'),
      ],
      '#default_value' => $this->options['calendar_display'] ?? 'month',
    ];

    $display_hour_state = [
      'invisible' => [
        'select[name="style_options[calendar_display]"]' => ['value' => 'month'],
      ],
      'required' => [
        ':input[name="style_options[calendar_display_hours]"]' => ['checked' => TRUE],
      ],
    ];

    $form['calendar_display_hours'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display hours'),
      '#default_value' => $this->options['calendar_display_hours'] ?? 1,
      '#states' => [
        'invisible' => [
          'select[name="style_options[calendar_display]"]' => ['value' => 'month'],
        ],
      ],
      '#access' => FALSE,
    ];
    $form['calendar_day_hours_start'] = [
      '#type' => 'select',
      '#title' => $this->t('Day starts at:'),
      '#options' => $hours,
      '#default_value' => $this->options['calendar_day_hours_start'] ?? 8,
      '#states' => $display_hour_state,
      '#access' => FALSE,
    ];
    $form['calendar_day_hours_end'] = [
      '#type' => 'select',
      '#title' => $this->t('Day ends at:'),
      '#options' => $hours,
      '#default_value' => $this->options['calendar_day_hours_end'] ?? 18,
      '#states' => $display_hour_state,
      '#access' => FALSE,
    ];
  }

  /**
   * Prepare the input values as an array "between" by default.
   *
   * @param mixed $input
   *   A given input value from View Exposed filters.
   * @param string $operator
   *   A given operator (default: between).
   *
   * @return array
   *   An array with min and max values, NULL by default.
   */
  public function processInput($input, string $operator = 'between') {
    // Defaults.
    $input_start = $input_end = NULL;
    $input_result = ['min' => NULL, 'max' => NULL];

    if (!empty($input)) {
      // String input.
      if (\is_string($input)) {
        $input_time = !ctype_digit(strval($input)) ? strtotime($input) : Xss::filter($input);
        $input_start = strtotime(date('Y-m-d 00:00:00', $input_time));
        $input_end = strtotime(date('Y-m-d 23:59:59', $input_time));
      }

      // Array input.
      if (\is_array($input)) {
        $input_min = $input['min'] ?? NULL;
        $input_max = $input['max'] ?? NULL;
        $input_start = $input_end = NULL;
        if (!empty($input_min)) {
          $input_time_min = !ctype_digit(strval($input_min)) ? strtotime($input_min) : Xss::filter($input_min);
          $input_start = strtotime(date('Y-m-d 00:00:00', $input_time_min));
        }
        if (!empty($input_max)) {
          $input_time_max = !ctype_digit(strval($input_max)) ? strtotime($input_max) : Xss::filter($input_max);
          $input_end = strtotime(date('Y-m-d 23:59:59', $input_time_max));
        }
      }
    }

    // Respect exposed filter.
    switch ($operator) {
      case 'between':
      case '<>':
      case '!=':
      case '=':
        $input_result = ['min' => $input_start, 'max' => $input_end];
        break;

      case '>=':
        $input_result = ['min' => $input_start, 'max' => NULL];
        break;

      case '>':
        $input_result = ['min' => $input_start + 1, 'max' => NULL];
        break;

      case '<=':
        $input_result = ['min' => NULL, 'max' => $input_end];
        break;

      case '<':
        $input_result = ['min' => NULL, 'max' => $input_end - 1];
        break;

      default:
        $input_result = ['min' => $input_start, 'max' => $input_end];
        break;
    }

    // Transform string to timestamp.
    foreach ($input_result as $key => $value) {
      if (!empty($value) && !ctype_digit(strval($value))) {
        $input[$key] = strtotime($value);
      }
    }

    return $input_result;
  }

  /**
   * Prepare values from a given result row.
   *
   * @param \Drupal\views\ResultRow $result
   *   A given row from a View.
   * @param \Drupal\views\Plugin\views\filter\Date $filter
   *   A given View filter.
   * @param int $delta
   *   Index of the field item to be processed.
   *
   * @return array
   *   The list of values usually representing field's items with:
   *   - calendar_filter: the name of the filter processing this result.
   *   - entity: the entity, or null.
   *   - item: the field's item instance, or null.
   *   - from: the start timestamp.
   *   - to: same as "from" timestamp by default.
   */
  public function processResult(ResultRow $result, DateFilter $filter, int $delta = 0) {
    $values = [];

    // Get real entity field's name.
    $field_name = $this->getFilterFieldname($filter);
    $filter_id = $filter->configuration['id'] ?? $filter->getPluginId();

    // Default values to support exotic Views structure.
    // (i.e`views_json_source`).
    if (isset($result->{$filter_id})) {
      $timestamp = $result->{$filter_id} ?? NULL;

      $values = [
        'calendar_filter' => $filter_id,
        'entity' => NULL,
        'item' => NULL,
        'from' => $timestamp,
        'to' => $timestamp,
      ];
    }

    // Get entity from result row.
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $this->getEntityByField($result, $field_name);

    // Entity not found from row.
    if (!$entity instanceof ContentEntityInterface || !$entity->hasField($field_name)) {
      return $values;
    }

    // Entity field found, but empty.
    $items = $entity->get($field_name);
    if ($items->isEmpty()) {
      return $values;
    }

    // For some reason, the delta is not correct for some field types such as
    // base field `created` or other specific fields such as `smart_date`.
    // So get first value (i.e. delta) because we know field is not empty.
    /** @var \Drupal\Core\Field\FieldItemBase $item */
    $item = $items->get($delta) ?? $items->first();

    $value = $item ? $item->getValue() : [];

    $values = [
      'item' => $item,
      'from' => $value['value'] ?? NULL,
      'to' => $value['end_value'] ?? $value['value'] ?? NULL,
      'calendar_filter' => $field_name,
      'entity' => $entity,
    ];

    return $values;
  }

  /**
   * Where the magic happens.
   *
   * @param \Drupal\views\Plugin\views\filter\Date $filter
   *   A given date filter.
   * @param array $results
   *   An array of ResultRow values.
   */
  public function processResults(DateFilter $filter, array $results = []) {
    $content = [];
    $min = $max = NULL;
    $filter_id = $filter->configuration['id'] ?? $filter->getPluginId();

    // Respect filters and user input, always using a "between" date array with
    // min and max by default to support advanced filtering.
    $default_value = $filter->value['value'] ?? [];
    $inputs = $this->view->getExposedInput();
    $input = $inputs[$filter_id] ?? $default_value;
    $operator = $filter->operator ?? 'between';
    $processed_input = $this->processInput($input, $operator);
    $min = $processed_input['min'] ?? NULL;
    $max = $processed_input['max'] ?? NULL;

    // Standard result row has the delta as property.
    // i.e. node__field_date_delta.
    $table_delta = $filter->table . '_delta' ?? NULL;

    foreach ($results as &$result) {
      try {
        $delta = $result->{$table_delta} ?? $result->index ?? 0;
        $values = $this->processResult($result, $filter, $delta);
      }
      catch (\Exception $e) {
        // Could not get the date field.
        $this->logger->error($e->getMessage() . PHP_EOL . Json::encode($result));
        continue;
      }

      // Insert occurence in content.
      if ($from = $values['from'] ?? NULL) {
        $time_date = date('Y-m-d 00:00:00', _calendar_view_convert_to_timestamp($from));
        $timestamp = (int) date('U', strtotime($time_date));

        // Respect limitations.
        $is_min_ok = (!$min || $min && $timestamp >= $min);
        $is_max_ok = (!$max || $max && $timestamp <= $max);
        if ($is_min_ok && $is_max_ok) {
          $content[$timestamp][] = $result;
        }
      }

      // Only if $to if higher.
      if (($to = $values['to'] ?? NULL) && $to > $from) {
        $date_time_from = new \DateTime();
        $date_time_from->setTimestamp(_calendar_view_convert_to_timestamp($from));

        $date_time_to = new \DateTime();
        $date_time_to->setTimestamp(_calendar_view_convert_to_timestamp($to));

        $interval = $date_time_to->diff($date_time_from);

        // Calculcate days span.
        $start = $interval->d == 0 ? 1 : 0;
        for ($i = $start; $interval->d >= $i; $i++) {
          $date_time_from->modify('+1 day');
          $date_time_from->modify('midnight');
          $timestamp = $date_time_from->getTimestamp();

          // Respect limitations.
          $is_min_ok = (!$min || $min && $timestamp >= $min);
          $is_max_ok = (!$max || $max && $timestamp <= $max);
          if ($is_min_ok && $is_max_ok) {
            $content[$timestamp][] = $result;
          }
        }
      }

      // Keep track of thing for later use.
      // @see template_preprocess_calendar_view_day()
      $result->calendar_view = $values;
    }

    // Sort chronologically.
    ksort($content);

    return $content;
  }

  /**
   * {@inheritDoc}
   */
  public function preRender($result) {
    parent::preRender($result);

    // Calendars might already been built.
    // The pager might have already been called by the ViewExecutable.
    // See \Drupal\calendar_view\Plugin\views\pager\Calendar::preRender().
    if (isset($this->view->calendars)) {
      return;
    }

    // Prepare calendar.
    $this->view->calendars = $this->buildCalendars($result);
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
   * Render a calendar.
   *
   * @todo How to start on another day?
   * @todo Make weekday label configurable.
   * @todo How to trim filter field name correctly?
   */
  public function buildCalendars($result) {
    // Check which exposed Date filters were selected.
    $available_date_filters = $this->getDateFilters();
    $calendar_filters = $this->options['calendar_filters'] ?? [];
    $calendar_filters = array_filter($calendar_filters, function ($field_name) use ($available_date_filters) {
      return ($field_name !== 0) && isset($available_date_filters[$field_name]);
    });
    if (!$calendar_filters) {
      if (!empty($available_date_filters)) {
        $keys = array_keys($available_date_filters);
        $default_calendar_filter_id = reset($keys);
        $this->options['calendar_filters'] = $calendar_filters = [$default_calendar_filter_id];
      }
      else {
        // Zero date fields available.
        $this->options['calendar_filters'] = $calendar_filters = [];
      }
    }

    $contents = [];
    foreach ($calendar_filters as $filter_id) {
      // Make sure date filter still exists on this View.
      $filter = $this->getDateFilters()[$filter_id] ?? NULL;
      if (!$filter instanceof DateFilter) {
        continue;
      }

      // Process results for calendar display.
      $list = $this->processResults($filter, $result);
      foreach ($list as $timestamp => $rows) {
        if (!isset($contents[$timestamp])) {
          $contents[$timestamp] = [];
        }
        $contents[$timestamp] += $rows;

        // Sort results in each cell.
        $sort_order = $this->options['calendar_sort_order'] ?? 'ASC';
        if ($sort_order == 'DESC') {
          krsort($contents[$timestamp]);
        }
        else {
          ksort($contents[$timestamp]);
        }
      }
    }

    // Make sure timestamps are valid.
    $timestamps = array_filter(array_keys($contents), function ($value) {
      return ctype_digit(strval($value));
    });

    // Use contextual timestamp, set in URl query or arguments.
    // @see \Drupal\calendar_views\Plugin\views\pager\Calendar::preRender()
    if ($selected_timestamp = $this->view->calendar_timestamp ?? NULL) {
      $selected_year = date('Y', $selected_timestamp);
      $selected_month = date('m', $selected_timestamp);
      $selected_month_start = strtotime($selected_year . '-' . $selected_month . '-' . '01');
      $selected_month_days = date('t', $selected_month_start);
      $selected_month_end = strtotime($selected_year . '-' . $selected_month . '-' . $selected_month_days);

      $timestamps = array_filter(array_keys($contents), function ($value) use ($selected_month_start, $selected_month_end) {
        return $value >= $selected_month_start && $value <= $selected_month_end;
      });
    }

    // Render an empty calendar if no results.
    if (empty($timestamps)) {
      $default_time = $selected_timestamp ?? $this->options['calendar_timestamp'] ?? date('U');
      if (!ctype_digit(strval($default_time))) {
        $default_time = strtotime($default_time);
      }
      $timestamps = [$default_time];
    }

    // Build calendars.
    $calendars = [];
    $calendar_display = $query['calendar_display'] ?? $this->options['calendar_display'] ?? 'month';
    foreach ($timestamps as $timestamp) {
      $year = date('Y', $timestamp);
      $month = date('m', $timestamp);
      $week = date('W', $timestamp);
      $day = date('j', $timestamp);

      switch ($calendar_display) {
        case 'agenda':
          break;

        case 'day':
          break;

        case 'week':
          if (!isset($calendars[$year . 'W' . $week])) {
            $calendars[$year . 'W' . $week] = $this->buildWeekTable($year, $week);
          }
          break;

        default:
          if (!isset($calendars[$year . $month])) {
            $calendars[$year . $month] = $this->buildMonthTable($year, $month);
          }
          break;
      }
    }

    // Populate calendars.
    foreach ($calendars as $key => $table) {
      foreach ($table['#rows'] ?? [] as $i => $rows) {
        foreach (array_keys($rows['data'] ?? []) as $timestamp) {
          $results = [];
          foreach ($contents[$timestamp] ?? [] as $index => $result) {
            // Render row and keep track of values for later use.
            // @see template_preprocess_calendar_view_day()
            $renderable_row = $this->view->rowPlugin->render($result);
            $renderable_row['#calendar_view'] = $result->calendar_view ?? [];
            $renderable_row['#view'] = $this->view;
            $results[] = $renderable_row;
          }

          $line = &$calendars[$key]['#rows'][$i];
          $cell = &$line['data'][$timestamp];
          $cell['data']['#children'] = $results;
        }
      }
    }

    return $calendars;
  }

  /**
   * Render a week calendar as a table.
   */
  public function buildWeekTable($year, $week) {
    $days = $this->getOrderedDays();

    $headers = [];
    foreach ($days as $number => $name) {
      $headers[$number] = $name;
    }

    // Dates for this week.
    $week_start = strtotime($year . 'W' . $week);
    $week_date = new \DateTime();
    $week_date->setTimestamp($week_start);

    $cells = [];
    $counter_date = clone $week_date;
    foreach (array_keys($headers) as $number) {
      $time_now = $counter_date->format('U');
      $counter_date->modify('+1 day');

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

    return $build;
  }

  /**
   * Render a month calendar as a table.
   */
  public function buildMonthTable($year, $month) {
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
