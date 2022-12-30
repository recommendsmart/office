<?php

namespace Drupal\datafield\Feeds\Target;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\feeds\FieldTargetDefinition;
use Drupal\feeds\Plugin\Type\Target\FieldTargetBase;

/**
 * Defines a field mapper for data Field.
 *
 * @FeedsTarget(
 *   id = "data_field",
 *   field_types = {"data_field"}
 * )
 */
class DataField extends FieldTargetBase {

  /**
   * {@inheritdoc}
   */
  protected static function prepareTarget(FieldDefinitionInterface $field_definition) {
    $ftd = FieldTargetDefinition::createFromFieldDefinition($field_definition);
    $sett = $field_definition->getSetting('field_settings');
    foreach ($sett as $f => $field) {
      $ftd->addProperty($f);
    }
    return $ftd;
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValue($delta, array &$values) {
    if (isset($values)) {
      $field_sett = $this->settings['field_settings'];
      foreach ($values as $name => $v) {
        if (isset($field_sett[$name]) && in_array($field_sett[$name]['type'],
            ['date', 'datetime'])
        ) {
          $new_val = $this->convertToDate($v);
          $storageFormat = $field_sett[$name]['type'] === 'date' ? DateTimeItemInterface::DATE_STORAGE_FORMAT : DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
          if (isset($new_val) && !$new_val->hasErrors()) {
            return $new_val->format($storageFormat, [
              'timezone' => DateTimeItemInterface::STORAGE_TIMEZONE,
            ]);
          }
          else {
            $new_val = '';
          }
          $values[$name] = $new_val;
        }
      }
      return $values;
    }
    else {
      throw new EmptyFeedException();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareValues(array $values) {
    $return = [];
    foreach ($values as $delta => $columns) {
      try {
        $this->prepareValue($delta, $columns);
        $return[] = $columns;
      }
      catch (EmptyFeedException $e) {
        // Nothing wrong here.
      }
      catch (TargetValidationException $e) {
        // Validation failed.
        $this->messenger()->addError($e->getMessage());

      }
    }
    return $return;
  }

  /**
   * Prepares a date value.
   *
   * @param string $value
   *   The value to convert to a date.
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime|null
   *   A datetime object or null, if there is no value or if the date value
   *   has errors.
   */
  protected function convertToDate($value) {
    $value = trim($value);

    // This is a year value.
    if (ctype_digit($value) && strlen($value) === 4) {
      $value = 'January ' . $value;
    }

    if (is_numeric($value)) {
      $date = DrupalDateTime::createFromTimestamp($value, DateTimeItemInterface::STORAGE_TIMEZONE);
    }
    elseif (strtotime($value)) {
      $date = new DrupalDateTime($value, DateTimeItemInterface::STORAGE_TIMEZONE);
      // $this->getTimezoneConfiguration());
    }
    if (isset($date) && !$date->hasErrors()) {
      return $date;
    }
  }

}
