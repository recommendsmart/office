<?php

namespace Drupal\storage;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the Storage entity.
 *
 * @see \Drupal\storage\Entity\Storage.
 */
class StorageAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\storage\Entity\StorageInterface $entity */

    if ($account->hasPermission('administer storage entities')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          $permission = $this->checkOwn($entity, 'view unpublished', $account);
          if (!empty($permission)) {
            return AccessResult::allowed()
              ->cachePerPermissions()
              ->cachePerUser()
              ->addCacheableDependency($entity);
          }

          return AccessResult::allowedIfHasPermission($account, 'view unpublished storage entities')
            ->cachePerPermissions()
            ->addCacheableDependency($entity);
        }

        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'view published storage entities',
            'view published ' . $entity->bundle() . ' storage entities',
          ],
          'OR'
        )
          ->cachePerPermissions()
          ->addCacheableDependency($entity);

      case 'update':

        $permission = $this->checkOwn($entity, $operation, $account);
        if (!empty($permission)) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'edit storage entities',
            'edit any ' . $entity->bundle() . ' storage entities',
          ],
          'OR'
        )
          ->cachePerPermissions()
          ->addCacheableDependency($entity);

      case 'delete':

        $permission = $this->checkOwn($entity, $operation, $account);
        if (!empty($permission)) {
          return AccessResult::allowed()
            ->cachePerPermissions()
            ->cachePerUser()
            ->addCacheableDependency($entity);
        }
        return AccessResult::allowedIfHasPermissions(
          $account,
          [
            'delete storage entities',
            'delete any ' . $entity->bundle() . ' storage entities',
          ],
          'OR'
        )
          ->cachePerPermissions()
          ->addCacheableDependency($entity);
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral()->cachePerPermissions();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermissions(
      $account,
      [
        'administer storage entities',
        'add storage entities',
        'add ' . (string) $entity_bundle . ' storage entities',
      ],
      'OR'
    );
  }

  /**
   * Check for given 'own' permissions.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for.
   * @param string $operation
   *   The operation to perform.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return string|null
   *   The permission string indicating it's allowed.
   */
  protected function checkOwn(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\storage\Entity\StorageInterface $entity */
    $uid = $entity->getOwnerId();

    $is_own = $account->isAuthenticated() && $account->id() == $uid;
    if (!$is_own) {
      return NULL;
    }

    $entity_ops = [
      'view unpublished' => 'view own unpublished storage entities',
      'update' => 'edit own storage entities',
      'delete' => 'delete own storage entities',
    ];
    $entity_permission = $entity_ops[$operation];
    if ($account->hasPermission($entity_permission)) {
      return $entity_permission;
    }

    $bundle_ops = [
      'view unpublished' => 'view own unpublished %bundle storage entities',
      'update' => 'edit own %bundle storage entities',
      'delete' => 'delete own %bundle storage entities',
    ];
    $bundle_permission = strtr($bundle_ops[$operation], ['%bundle' => $entity->bundle()]);

    if ($account->hasPermission($bundle_permission)) {
      return $bundle_permission;
    }

    return NULL;
  }

}
