<?php

declare(strict_types=1);

namespace Drupal\date_filter\Plugin\views\filter;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\NumericFilter;
use Drupal\views\ViewExecutable;

/**
 * An improved Date/time views filter base class.
 *
 * Based on Drupal\datetime\Plugin\views\filter\Date, hopefully
 * it'll replace its origin one day.
 */
abstract class DateBase extends NumericFilter {

  /**
   * Some datetime fields don't use time at all.
   */
  protected bool $noTime = FALSE;

  /**
   * Does this filter use date only? UI setting.
   */
  protected bool $skipTimeUi = TRUE;

  /**
   * Data type, date or timestamp.
   */
  protected string $dataType;

  /**
   * Possible value elements array, key -> label.
   *
   * @var \Drupal\Core\StringTranslation\TranslatableMarkup[]
   */
  protected array $valueElements;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL) {
    parent::init($view, $display, $options);

    if ($this->options['type'] === 'datetime') {
      $this->skipTimeUi = FALSE;
    }

    $this->valueElements = [
      'value' => NULL,
      'min' => $this->t('from'),
      'max' => $this->t('to'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function operators() {
    $operators = parent::operators();
    // We don't need a regex in a date filter.
    unset($operators['regular_expression']);

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['type'] = [
      'default' => 'date',
    ];

    unset($options['expose']['contains']['placeholder']);
    unset($options['expose']['contains']['min_placeholder']);
    unset($options['expose']['contains']['max_placeholder']);
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    // This could be a checkbox really but we're staying with type
    // property and radios so we don't have to do anything with date
    // and datetime config schema.
    // @todo If this'll ever make its way to core, config update will be
    // needed to convert this to boolean.
    $form['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Filter type'),
      '#default_value' => $this->skipTimeUi ? 'date' : 'datetime',
      '#options' => [
        'date' => $this->t('Date'),
        'datetime' => $this->t('Date and time'),
      ],
    ];
    if ($this->noTime) {
      $form['type']['#disabled'] = TRUE;
      $form['type']['#description'] = $this->t('This is a date-only field.');
    }

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);

    unset($form['expose']['min_placeholder']);
    unset($form['expose']['max_placeholder']);
    unset($form['expose']['placeholder']);
  }

  /**
   * {@inheritdoc}
   */
  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    parent::validateOptionsForm($form, $form_state);

    if (empty($this->options['expose']['required'])) {
      return;
    }

    $operators = $this->operators();
    $operator = $form_state->getValue(['options', 'operator']);
    if ($operators[$operator]['values'] == 1) {
      $validate_keys = ['value'];
    }
    elseif ($operators[$operator]['values'] == 2) {
      $validate_keys = ['min', 'max'];
    }

    // Validate that the time values convert to something usable.
    $value = $form_state->getValue(['options', 'value']);
    foreach ($validate_keys as $validate_key) {
      if (!\array_key_exists($validate_key, $form['value'])) {
        continue;
      }
      $convert = \strtotime($value[$validate_key]);
      if ($convert == -1 || $convert === FALSE) {
        $form_state->setError($form['value'][$validate_key], $this->t('Invalid date format.'));
      }
    }
  }

  /**
   * Add a type selector to the value form.
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    parent::valueForm($form, $form_state);

    if ($form_state->get('exposed') !== TRUE) {
      foreach (\array_keys($this->valueElements) as $value_key) {
        $form['value'][$value_key]['#description'] = $this->t('A date in any machine readable format (CCYY-MM-DD HH:MM:SS is preferred) or an offset from the current time such as "@example1" or "@example2".', [
          '@example1' => '+1 day',
          '@example2' => '-2 hours -30 minutes',
        ]);
      }
      return;
    }

    // Now we have end-user facing value form without Views UI modal
    // limitations and offset needs so datepickers are much nicer.
    // @todo Conditionally display datepickers if default value type
    // is not an offset in Views admin UI as well.
    // @todo Predefined offset options?
    $input = $form_state->getUserInput();
    $identifier = $this->options['expose']['identifier'];
    $filter_input = [];
    if ($input === NULL) {
      $input = [];
    }
    if (!\array_key_exists($identifier, $input)) {
      $input[$identifier] = [];
    }
    $filter_input = &$input[$identifier];
    if (
      \is_string($filter_input) ||
      \array_key_exists('date', $filter_input)
    ) {
      $filter_input = [
        'value' => $filter_input,
      ];
    }

    if (\array_key_exists('#type', $form['value'])) {
      $form['value'] = ['#tree' => TRUE];
      $element = &$form;
    }
    else {
      foreach (['min', 'max'] as $value_key) {
        unset($form['value'][$value_key]['#type']);
        $form['value'][$value_key]['#theme'] = 'datetime_form';
        $form['value'][$value_key]['#theme_wrappers'] = ['datetime_wrapper'];
      }
      $element = &$form['value'];
    }

    $formats = \explode('\T', $this->getWidgetDateFormat());

    foreach ($this->valueElements as $value_key => $label) {
      if (!\array_key_exists($value_key, $element)) {
        continue;
      }

      if (!\array_key_exists($value_key, $filter_input)) {
        $filter_input[$value_key] = $this->value[$value_key];
      }

      if (\is_string($filter_input[$value_key])) {
        $date = $this->getDate($filter_input[$value_key]);
        $filter_input[$value_key] = [
          'date' => $date === NULL ? '' : $date->format($formats[0]),
        ];
        if (!$this->skipTimeUi) {
          $filter_input[$value_key]['time'] = $date === NULL ? '' : $date->format($formats[1]);
        }
      }

      $element[$value_key]['#title'] = $label;

      // Don't use a datetime element as it results in a DrupalDateTime in
      // form state values and array of strings in user input while Views
      // uses both as they were equivalent (probably a core bug candidate).
      // Date elements on the other hand don't convert the user input in any
      // way so the only conversion to DrupalDateTime happens here. It's
      // possibly also a performance improvement.
      $element[$value_key]['date'] = [
        '#type' => 'date',
        '#title' => $this->t('Date'),
        '#title_display' => 'invisible',
        '#attributes' => ['type' => 'date'],
        '#date_date_format' => $formats[0],
        '#default_value' => $filter_input[$value_key]['date'],
      ];

      if (!$this->skipTimeUi) {
        $element[$value_key]['#process'][] = [
          RenderElement::class,
          'processGroup',
        ];

        $element[$value_key]['time'] = [
          '#type' => 'date',
          '#title' => 'Time',
          '#title_display' => 'invisible',
          '#attributes' => ['type' => 'time', 'step' => 1],
          '#default_value' => $filter_input[$value_key]['time'],
        ];
      }

    }

    $form_state->setUserInput($input);
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    if (empty($this->options['exposed'])) {
      return TRUE;
    }

    $value = $input[$this->options['expose']['identifier']];
    if (\is_array($value) && \array_key_exists('date', $value)) {
      $value = [
        'value' => $value,
      ];
    }
    $this->value = $value;

    if ($this->operator === 'between' || $this->operator === 'not between') {
      $one_has_value = FALSE;
      foreach (['min', 'max'] as $key) {
        if (
          \is_array($value) &&
          \array_key_exists($key, $value) &&
          \array_key_exists('date', $value[$key])
        ) {
          $one_has_value = TRUE;
          break;
        }
      }
      return $one_has_value;
    }
    elseif (
      \is_array($value) &&
      \array_key_exists('value', $value) &&
      \array_key_exists('date', $value['value'])
    ) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opBetween($field): void {
    $value = [];
    foreach (['min', 'max'] as $value_key) {
      $value[$value_key] = $this->processValue($this->value[$value_key], $value_key);
    }

    if ($value['min'] !== NULL && $value['max'] !== NULL) {
      $operator = \strtoupper($this->operator);
      $this->query->addWhereExpression($this->options['group'], "$field $operator {$value['min']} AND {$value['max']}");
    }
    elseif ($value['min'] !== NULL) {
      $this->query->addWhereExpression($this->options['group'], "$field >= {$value['min']}");
    }
    elseif ($value['max'] !== NULL) {
      $this->query->addWhereExpression($this->options['group'], "$field <= {$value['max']}");
    }
  }

  /**
   * Override parent method, which deals with dates as integers.
   */
  protected function opSimple($field): void {
    // Special cases where we want to find content from a specific date.
    if ($this->operator === '=' && $this->skipTimeUi) {
      $this->value['min'] = $this->value['max'] = $this->value['value'];
      $this->operator = 'between';
      $this->opBetween($field);
      return;
    }
    if ($this->operator === '!=' && $this->skipTimeUi) {
      $this->value['min'] = $this->value['max'] = $this->value['value'];
      $this->operator = 'not between';
      $this->opBetween($field);
      return;
    }

    $value = $this->processValue($this->value['value']);
    if ($value === NULL) {
      return;
    }

    $this->query->addWhereExpression($this->options['group'], "$field $this->operator $value");
  }

  /**
   * Helper function that gets preprocessed date from provided value.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime|string|null $value
   *   The input value to process.
   * @param string $value_key
   *   Type of value: min, max or value.
   */
  protected function getProcessedDate($value, string $value_key = ''): ?DrupalDateTime {
    if ($value === NULL) {
      return NULL;
    }

    // Admin UI value is a string, exposed value is an array containing
    // date and time keys.
    if (\is_array($value)) {
      $value = \trim(\implode(' ', $value));
    }
    $date = $this->getDate($value);
    if (!$date instanceof DrupalDateTime || $date->hasErrors()) {
      return NULL;
    }

    // Special case: date-only. We need to set times.
    if ($this->skipTimeUi) {
      $this->resetTimes($date, $value_key);
    }

    return $date;
  }

  /**
   * Converts value to query format.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime|string|null $value
   *   The input value to process.
   * @param string $value_key
   *   Type of value: min, max or value.
   */
  abstract protected function processValue($value, string $value_key = ''): ?string;

  /**
   * Reset times for proper date filtering.
   */
  protected function resetTimes(DrupalDateTime $date, string $value_key): void {
    if ($this->operator === '=' || $this->operator === '!=') {
      return;
    }

    if ($this->operator === '>' || $this->operator === '>=') {
      $date->setTime(0, 0, 0);
    }
    if ($this->operator === '<' || $this->operator === '<=') {
      $date->setTime(23, 59, 59);
    }
    if ($this->operator === 'between' || $this->operator === 'not between') {
      if ($value_key === 'min') {
        $date->setTime(0, 0, 0);
      }
      elseif ($value_key === 'max') {
        $date->setTime(23, 59, 59);
      }
    }

  }

  /**
   * Get date from offset to use in computations.
   *
   * @param string $date_string
   *   The date offset.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   DrupalDateTime object representing user input or NULL.
   */
  protected function getDate(string $date_string): ?DrupalDateTime {
    if ($date_string === '') {
      return NULL;
    }
    $date_string = \trim($date_string);
    $date = new DrupalDateTime($date_string, $this->getInputTimezone());

    return $date;
  }

  /**
   * Get datetime storage format.
   */
  private function getWidgetDateFormat(): string {
    if ($this->skipTimeUi) {
      return 'Y-m-d';
    }
    return 'Y-m-d\TH:i:s';
  }

  /**
   * Gets time zone depending on date storage type.
   *
   * The general rule here is that date-only datetime fields
   * need to ignore the user time zone and treat the input as
   * it was in the starage time zone. Everything else respects
   * time zones.
   */
  abstract protected function getInputTimezone(): string;

}
