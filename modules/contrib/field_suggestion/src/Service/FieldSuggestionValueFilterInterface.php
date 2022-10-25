<?php

namespace Drupal\field_suggestion\Service;

/**
 * Defines the field values filter service interface.
 */
interface FieldSuggestionValueFilterInterface {

  /**
   * Whether this filter should be used to exclude field values.
   *
   * @param $entity_type
   *   The entity type identifier.
   * @param $field_name
   *   The field name.
   *
   * @return bool
   *   TRUE if this ignorer should be used or FALSE to let other filters decide.
   */
  public function applies($entity_type, $field_name);

  /**
   * Provides field values that should be excluded from the suggestions list.
   *
   * @param $entity_type
   *   The entity type identifier.
   * @param $field_name
   *   The field name.
   *
   * @return array
   *   The values list.
   */
  public function items($entity_type, $field_name);

}
