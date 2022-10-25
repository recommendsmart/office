<?php

namespace Drupal\field_suggestion;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class to build a listing of field suggestion entities.
 *
 * @see \Drupal\field_suggestion\Entity\FieldSuggestion
 */
class FieldSuggestionListBuilder extends EntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The helper.
   *
   * @var \Drupal\field_suggestion\Service\FieldSuggestionHelperInterface
   */
  protected $helper;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(
    ContainerInterface $container,
    EntityTypeInterface $entity_type
  ) {
    $instance = parent::createInstance($container, $entity_type);

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->helper = $container->get('field_suggestion.helper');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    return [
      'type' => $this->t('Type'),
      'entity_type' => $this->t('Entity type'),
      'field_name' => $this->t('Field name'),
      'field_value' => $this->t('Field value'),
      'usage' => $this->t('Usage'),
      'exclude' => $this->t('Exclude'),
    ] + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $row = [];

    /** @var \Drupal\field_suggestion\FieldSuggestionInterface $entity */
    $row['type'] = ($ignore = $entity->isIgnored())
      ? $this->t('Ignored') : $this->t('Pinned');

    $row['entity_type'] = $this->label(
      $this->entityTypeManager->getDefinitions(),
      $entity_type = $entity->type()
    );

    $row['field_name'] = $this->label(
      $this->entityFieldManager->getBaseFieldDefinitions($entity_type),
      $entity->field()
    );

    $row['field_value'] = $entity->label();
    $row['usage'] = $ignore ? '-' : ($entity->isOnce() ? 1 : '∞');
    $row['exclude'] = $ignore ? '-' : $entity->countExcluded();

    return $row + parent::buildRow($entity);
  }

  /**
   * Provide cell text based on a labeled object as a field.
   *
   * @param array $definitions
   *   The definitions of entity types or fields.
   * @param string $name
   *   The name of entity type or field.
   *
   * @return string
   *   The label if a definition is found. Otherwise, a received name.
   */
  protected function label($definitions, $name) {
    if (isset($definitions[$name])) {
      $name = $definitions[$name]->getLabel() . ' (' . $name. ')';
    }

    return $name;
  }

}
