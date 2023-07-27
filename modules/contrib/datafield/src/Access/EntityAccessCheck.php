<?php

namespace Drupal\datafield\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\datafield\Plugin\Field\FieldFormatter\Base;

/**
 * Provides an access checker for datafield operations.
 */
class EntityAccessCheck implements AccessInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a Permissions object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Checks access to the datafield operation on the given route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check against.
   * @param string $field_name
   *   The field name to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(AccountInterface $account, EntityInterface $entity, string $field_name) {
    $entityAccess = $entity->access('update', $account, TRUE);

    if ($entity instanceof FieldableEntityInterface && $entity->hasField($field_name)) {
      if ($this->moduleHandler->moduleExists('field_permissions')) {
        $hasPermission = Base::checkPermissionOperation($entity, $field_name);
        return $hasPermission ? new AccessResultAllowed() : new AccessResultForbidden();
      }
      $fieldAccess = $entity->get($field_name)->access('edit', $account, TRUE);
      return $entityAccess->andIf($fieldAccess);
    }

    return $entityAccess;
  }

}
