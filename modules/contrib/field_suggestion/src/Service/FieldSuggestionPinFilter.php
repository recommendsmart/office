<?php

namespace Drupal\field_suggestion\Service;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the pinned suggestion filter service.
 */
class FieldSuggestionPinFilter extends FieldSuggestionFilterBase implements FieldSuggestionPinFilterInterface {

  /**
   * {@inheritdoc}
   */
  function exclude(array $excluded_entities, ContentEntityInterface $entity) {
    foreach ($this->filters as $filter_id) {
      $filter = $this->classResolver->getInstanceFromDefinition($filter_id);

      if ($filter->exclude($excluded_entities, $entity)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
