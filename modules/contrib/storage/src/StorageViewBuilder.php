<?php

namespace Drupal\storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\storage\Entity\StorageInterface;

/**
 * View builder handler for storage entities.
 */
class StorageViewBuilder extends EntityViewBuilder {

  /**
   * {@inheritdoc}
   */
  protected function addContextualLinks(array &$build, EntityInterface $entity) {
    if ($entity->isNew() || !($entity instanceof StorageInterface)) {
      return;
    }
    $key = $entity->getEntityTypeId();
    $rel = 'canonical';
    if (!$entity->isDefaultRevision()) {
      $rel = 'revision';
      $key .= '_revision';
    }

    $build['#contextual_links'][$key] = [
      'route_parameters' => [$key => $entity->id()],
      'metadata' => ['changed' => $entity->getChangedTime()],
    ];
    if ($entity->hasLinkTemplate($rel) && $entity->toUrl($rel)->isRouted()) {
      $build['#contextual_links'][$key]['route_parameters'] = $entity->toUrl($rel)->getRouteParameters();
    }
  }

}
