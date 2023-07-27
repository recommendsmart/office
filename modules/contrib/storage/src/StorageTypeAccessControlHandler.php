<?php

namespace Drupal\storage;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for the Storage type entity type.
 *
 * @see \Drupal\storage\Entity\StorageType
 */
class StorageTypeAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    switch ($operation) {

      case 'view':
        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'view published storage entities',
            'view published ' . $entity->id() . ' storage entities',
            'view unpublished storage entities',
            'view unpublished ' . $entity->id() . ' storage entities',
            'view own unpublished storage entities',
            'view own unpublished ' . $entity->id() . ' storage entities',
            \Drupal::entityTypeManager()->getDefinition($entity->getEntityType()->getBundleOf())->getAdminPermission(),
            $entity->getEntityType()->getAdminPermission(),
          ],
          'OR'
        )
          ->cachePerPermissions()
          ->addCacheableDependency($entity);

      default:
        return parent::checkAccess($entity, $operation, $account);

    }
  }

}
