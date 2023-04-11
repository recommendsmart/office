<?php

declare(strict_types=1);

namespace Drupal\date_filter\Plugin\views\filter;

use Drupal\datetime\Plugin\Field\FieldType\DateTimeItem;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\views\FieldAPIHandlerTrait;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ViewExecutable;

/**
 * An improved Datetime views filter class.
 *
 * Based on Drupal\datetime\Plugin\views\filter\Date, hopefully
 * it'll replace its origin one day.
 *
 * @ingroup views_filter_handlers
 */
final class DateTime extends DateBase {

  use FieldAPIHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);

    // We need definition for base fields as well as the trait method
    // relies on this property.
    if (!\array_key_exists('field_name', $this->definition)) {
      $this->definition['field_name'] = $this->definition['entity field'];
    }
    $definition = $this->getFieldStorageDefinition();

    if ($definition->getSetting('datetime_type') === DateTimeItem::DATETIME_TYPE_DATE) {
      $this->noTime = TRUE;
    }
    $this->skipTimeUi = TRUE;
    if ($this->options['type'] === 'datetime' && !$this->noTime) {
      $this->skipTimeUi = FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function processValue($value, string $value_key = ''): ?string {
    $date = $this->getProcessedDate($value, $value_key);
    if ($date === NULL) {
      return NULL;
    }

    $format = $this->getStorageFormat();
    $date_value = $date->format($format, [
      'timezone' => $this->getStorageTimezone(),
    ]);

    $date_field = $this->query->getDateField(
      "'" . $date_value . "'",
      TRUE,
      FALSE
    );

    return $this->query->getDateFormat($date_field, $format, TRUE);
  }

  /**
   * Gets datetime storage format.
   */
  protected function getStorageFormat(): string {
    if ($this->noTime) {
      return DateTimeItemInterface::DATE_STORAGE_FORMAT;
    }
    return DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
  }

  /**
   * Gets datetime storage timezone.
   */
  protected function getStorageTimezone() {
    return DateTimeItemInterface::STORAGE_TIMEZONE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getInputTimezone(): string {
    if (!$this->noTime) {
      return \date_default_timezone_get();
    }
    return DateTimeItemInterface::STORAGE_TIMEZONE;
  }

}
