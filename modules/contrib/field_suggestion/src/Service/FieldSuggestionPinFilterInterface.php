<?php

namespace Drupal\field_suggestion\Service;

use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Defines the pinned suggestion filter service interface.
 */
interface FieldSuggestionPinFilterInterface {

  /**
   * Exclude a pinned suggestion for the selected entity.
   *
   * @param array $excluded_entities
   *   The excluded entities list.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity object.
   *
   * @return bool
   *   TRUE if suggestion should be excluded.
   */
  function exclude(array $excluded_entities, ContentEntityInterface $entity);

}
