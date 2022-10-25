<?php

namespace Drupal\field_suggestion\Service;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Defines the helper service interface.
 */
interface FieldSuggestionHelperInterface extends SerializationInterface {

  /**
   * FieldSuggestionHelper constructor.
   *
   * @param \Drupal\field_suggestion\Service\FieldSuggestionValueFilterInterface $filter
   *   The field values filter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   */
  public function __construct(
    FieldSuggestionValueFilterInterface $filter,
    EntityTypeManagerInterface $entity_type_manager,
    EntityDisplayRepositoryInterface $entity_display_repository
  );

  /**
   * Gets field values that should be excluded from the suggestions list.
   *
   * @param $entity_type
   *   The entity type identifier.
   * @param $field_name
   *   The field name.
   *
   * @return string[]
   *   The values list.
   */
  public function ignored($entity_type, $field_name);

  /**
   * Create a bundle.
   *
   * @param string $field_type
   *   The field type.
   */
  public function bundle($field_type);

  /**
   * Create the name of the field for storing suggestion value.
   *
   * @return string
   *   The field name.
   */
  public function field($type);

}
