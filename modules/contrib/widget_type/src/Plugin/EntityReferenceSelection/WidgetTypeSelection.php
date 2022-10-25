<?php

namespace Drupal\widget_type\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Annotation\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Provides specific access control for the widget type entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:widget_type",
 *   label = @Translation("Widget type selection"),
 *   entity_types = {"widget_type"},
 *   group = "default",
 *   weight = 1
 * )
 */
class WidgetTypeSelection extends DefaultSelection {

  public function defaultConfiguration() {
    return [
        'widget_type_fields' => [],
      ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $configuration = $this->getConfiguration();
    $target_type = $configuration['target_type'];
    $entity_type = $this->entityTypeManager->getDefinition($target_type);

    $query = $this->entityTypeManager->getStorage($target_type)->getQuery();
    $query->accessCheck(TRUE);

    // If 'target_bundles' is NULL, all bundles are referenceable, no further
    // conditions are needed.
    if (is_array($configuration['target_bundles'])) {
      // If 'target_bundles' is an empty array, no bundle is referenceable,
      // force the query to never return anything and bail out early.
      if ($configuration['target_bundles'] === []) {
        $query->condition($entity_type->getKey('id'), NULL, '=');
        return $query;
      }
      else {
        $query->condition($entity_type->getKey('bundle'), $configuration['target_bundles'], 'IN');
      }
    }

    // Add entity-access tag.
    $query->addTag($target_type . '_access');

    // Add the Selection handler for system_query_entity_reference_alter().
    $query->addTag('entity_reference');
    $query->addMetaData('entity_reference_selection_handler', $this);

    // Add the sort option.
    if ($configuration['sort']['field'] !== '_none') {
      $query->sort($configuration['sort']['field'], $configuration['sort']['direction']);
    }

    $widget_type_fields = $configuration['widget_type_fields'];
    if (empty($widget_type_fields)) {
      return $query;
    }

    $or = $query->orConditionGroup();
    foreach ($widget_type_fields as $key) {
      $or->condition($key, $match, $match_operator);
    }
    $query->condition($or);

    return $query;
  }


  public function getReferenceableEntities($match = '', $match_operator = 'CONTAINS', $limit = 0) {
    if ($match === '') {
      return parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    $query = $this->buildEntityQuery($match, $match_operator);
    if ($limit > 0) {
      $query->range(0, $limit);
    }

    $result = $query->execute();

    if (empty($result)) {
      return [];
    }

    $options = [];
    $entities = $this->entityTypeManager->getStorage('widget_type')->loadMultiple($result);
    foreach ($entities as $entity_id => $entity) {
      $bundle = $entity->bundle();
      $options[$bundle][$entity_id] = Html::escape(
        $this->entityRepository->getTranslationFromContext($entity)->label() ?? ''
      );
    }

    return $options;
  }
}
