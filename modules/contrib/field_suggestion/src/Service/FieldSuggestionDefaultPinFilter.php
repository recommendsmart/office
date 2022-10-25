<?php

namespace Drupal\field_suggestion\Service;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the default pinned suggestion filter service.
 */
class FieldSuggestionDefaultPinFilter implements FieldSuggestionPinFilterInterface {

  /**
   * {@inheritdoc}
   */
  function exclude(array $excluded_entities, ContentEntityInterface $entity) {
    if (!$entity->isNew()) {
      foreach ($excluded_entities as $excluded_entity) {
        if (
          $excluded_entity['target_type'] === $entity->getEntityTypeId() &&
          $excluded_entity['target_id'] === $entity->id()
        ) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

}
