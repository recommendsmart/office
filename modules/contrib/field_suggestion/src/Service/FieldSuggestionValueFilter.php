<?php

namespace Drupal\field_suggestion\Service;

/**
 * Defines the field values filter service.
 */
class FieldSuggestionValueFilter extends FieldSuggestionFilterBase implements FieldSuggestionValueFilterInterface {

  /**
   * {@inheritdoc}
   */
  public function applies($entity_type, $field_name) {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function items($entity_type, $field_name) {
    $fields = [];

    foreach ($this->filters as $filter_id) {
      $filter = $this->classResolver->getInstanceFromDefinition($filter_id);

      if ($filter->applies($entity_type, $field_name)) {
        $fields = array_merge($fields, $filter->items($entity_type, $field_name));
      }
    }

    return $fields;
  }

}
