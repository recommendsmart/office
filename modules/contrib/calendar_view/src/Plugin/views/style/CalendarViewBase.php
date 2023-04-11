<?php

namespace Drupal\calendar_view\Plugin\views\style;

use Drupal\calendar_view\Plugin\views\pager\CalendarViewPagerInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\EntityField;
use Drupal\views\Plugin\views\style\DefaultStyle;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a base class for Calendar View style plugin.
 */
abstract class CalendarViewBase extends DefaultStyle implements CalendarViewInterface {

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
   * A list of already processed result by field.
   *
   * @var array
   */
  protected $processedResultsByField = [];

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
   * Helper method to make sure a timestamp is a timestamp.
   *
   * @param mixed $value
   *   A given value.
   *
   * @return mixed
   *   The timestamp or the original value.
   */
  public function isTimestampValue($value) {
    return !empty($value) && !ctype_digit(strval($value)) ? strtotime($value) : $value;
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
      0 => $this->t('Sunday'),
      1 => $this->t('Monday'),
      2 => $this->t('Tuesday'),
      3 => $this->t('Wednesday'),
      4 => $this->t('Thursday'),
      5 => $this->t('Friday'),
      6 => $this->t('Saturday'),
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
   * {@inheritDoc}
   */
  public function getCalendarTimestamp(): string {
    // Avoid unnecessary calls with static variable.
    $timestamp = &drupal_static(__FUNCTION__);
    if (isset($timestamp)) {
      return $timestamp;
    }

    // Allow user to pass query string.
    // (i.e "<url>?calendar_timestamp=2022-12-31").
    $selected_timestamp = $this->view->getExposedInput()['calendar_timestamp'] ?? NULL;

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

    return $this->isTimestampValue($timestamp);
  }

  /**
   * Helper to render the message when no fields available.
   *
   * @return array
   *   The message as render array.
   */
  public function getOutputNoFields() {
    $view_edit_url = Url::fromRoute('entity.view.edit_form', ['view' => $this->view->id()]);

    $build = [];

    $build['#markup'] = $this->t('Missing calendar field.');
    $build['#markup'] .= '<br>';
    $build['#markup'] .= $this->t('Please select at least one field in the @link.', [
      '@link' => Link::fromTextAndUrl(
        $this->t('Calendar View settings'),
        $view_edit_url,
      )->toString(),
    ]);

    $build['#access'] = $view_edit_url->access();

    return $build;
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
      // Allow user to reduce page load.
      'calendar_query_filtering' => 1,
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

    $form['calendar_query_filtering'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Performance: filter query by dates'),
      '#description' => $this->t('If enabled, this View query will be filtered by +/- one month/week, depending on the selected calendar display.') . '<br>' .
        $this->t('It is recommended to enable this option as it greatly reduces page load for large results sets (e.g. recurring events).') . '<br>' .
        $this->t('<b>Warning: does not work for date fields from a relationship (see @link)</b>', [
          '@link' => Link::fromTextAndUrl($this->t('this bug'), Url::fromUri('https://www.drupal.org/i/3350219', [
            'attributes' => ['target' => '_blank'],
          ]))->toString()
        ]) . '<br>' .
        $this->t('Leave this option uncheck if your Calendar date fields are attached through a relationship (e.g. a date from a pararaph).'),
      '#default_value' => $this->options['calendar_query_filtering'] ?? 0,
    ];

  }

  /**
   * {@inheritDoc}
   */
  public function processResult(ResultRow $result, EntityField $field): array {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $field->getEntity($result);

    $config = $field->configuration ?? [];
    $field_id = $config['field_name'] ?? $config['entity field'] ?? $config['id'] ?? NULL;
    $items = $entity->hasField($field_id) ? $entity->get($field_id) : NULL;

    // Get values from entity or default method from EntityField View plugin.
    $field_values = $items instanceof FieldItemListInterface ? $items->getValue() : $field->getValue($result);

    // Always wrap field values in a list for consistency in the process below.
    $field_values = !\is_array($field_values) ? [$field_values] : $field_values;

    // Skip already processed field values.
    $key = $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $field_id;
    if (in_array($key, $this->processedResultsByField)) {
      return [];
    }

    // Skip empty result.
    if ($field->isValueEmpty($field_values, TRUE)) {
      return [];
    }

    $rows = [];
    $values = [];
    foreach ($field_values as $delta => $value) {
      $values[$delta] = [];
      $values[$delta]['entity'] = $entity;
      $values[$delta]['calendar_field'] = $field;

      // Get a unique identifier for this event.
      $result_hash = md5(serialize($result) . $field_id . $delta);
      $values[$delta]['hash'] = $result_hash;

      // Get event timestamp(s).
      $from = $values[$delta]['from'] = $this->isTimestampValue($value['value'] ?? $value);
      $to = $values[$delta]['to'] = $this->isTimestampValue($value['end_value'] ?? NULL);

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

    // Reduce work and avoid duplicates (e.g. recurring events).
    $this->processedResultsByField[] = $key;

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

    // Stop now if no field selected.
    if (empty($calendar_fields)) {
      $output = $this->getOutputNoFields();
      $this->view->calendars = [$output];
      $this->view->calendar_error = TRUE;
      return;
    }

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
      }
    }

    // Build calendars.
    $calendars = $this->buildCalendars($this->getCalendarTimestamp());

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
   * {@inheritdoc}
   */
  public function evenEmpty() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    parent::query();

    $fields = $this->view->style_plugin->options['calendar_fields'] ?? [];
    if (empty($fields)) {
      return;
    }

    // @todo To be removed when https://www.drupal.org/i/3350219 is fixed.
    if (!($this->view->style_plugin->options['calendar_query_filtering'] ?? TRUE)) {
      return;
    }

    // Filter query only for our custom pagers.
    $pager = $this->view->display_handler->getPlugin('pager');
    if (!$pager instanceof CalendarViewPagerInterface) {
      return;
    }

    $now = new \DateTime();
    $now->setTimestamp($this->getCalendarTimestamp());

    $start = $this->getQueryDatetimeStart($now);
    $end = $this->getQueryDatetimeEnd($now);

    // Multiple search fields are ORed together.
    $conditions = $this->view->query->getConnection()->condition('OR');

    foreach ($fields as $field_id) {
      /** @var \Drupal\views\Plugin\views\field\EntityField $field */
      $field = $this->view->field[$field_id] ?? NULL;
      if (!$field) {
        continue;
      }

      // This ensures $alias includes the table name.
      $field->ensureMyTable();
      $alias = $field->getField();

      // Workaround for missing suffix.
      // @todo Find a better fix and contribute to bookable_calendar module.
      if (in_array($field->tableAlias, ['bookable_calendar_opening_inst'])) {
        $alias .= '__value';
      }

      if ($base_field = $field->options['relationship']) {
        $relationship = $this->view->relationship[$base_field] ?? NULL;
      }

      // Add an OR condition for the field.
      $and = $this->view->query->getConnection()->condition('AND');
      $and->condition($alias, $start->getTimestamp(), '>');
      $and->condition($alias, $end->getTimestamp(), '<');
      $conditions->condition($and);
    }

    $this->view->query->addWhere(0, $conditions);
  }

  /**
   * Start value used in the query() method to restrict results.
   *
   * Get results in the current month only by default.
   *
   * @param \Datetime $now
   *   A given datetime.
   *
   * @return \Datetime
   *   A new datetime object.
   */
  public function getQueryDatetimeStart(\Datetime $now): \Datetime {
    $start = clone $now;

    $start
      ->modify('-1 month')
      ->modify('last day of this month')
      // Potential first days of previous month month appearing in calendar.
      ->modify('-6 days')
      ->setTime(23, 59, 59);

    return $start;
  }

  /**
   * End value used in the query() method to restrict results.
   *
   * Get results in the current month only by default.
   *
   * @param \Datetime $now
   *   A given datetime.
   *
   * @return \Datetime
   *   A new datetime object.
   */
  public function getQueryDatetimeEnd(\Datetime $now): \Datetime {
    $end = clone $now;

    $end
      ->modify('+1 month')
      ->modify('first day of this month')
      // Potential first days of next month appearing in calendar.
      ->modify('+6 days')
      ->setTime(0, 0, 0);

    return $end;
  }

}
