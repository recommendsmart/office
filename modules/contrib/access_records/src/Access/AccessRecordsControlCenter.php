<?php

namespace Drupal\access_records\Access;

use Drupal\access_records\AccessRecordQueryBuilder;
use Drupal\access_records\Entity\AccessRecordType;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Check access by defined access records.
 */
class AccessRecordsControlCenter {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Get the access records control center service.
   *
   * @return \Drupal\access_records\Access\AccessRecordsControlCenter
   *   The service instance.
   */
  public static function get(): AccessRecordsControlCenter {
    return \Drupal::service('access_records.control_center');
  }

  /**
   * The AccessRecordsControlCenter constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher.
   */
  public function __construct(EntityTypeManagerInterface $etm, AccountInterface $current_user) {
    $this->entityTypeManager = $etm;
    $this->currentUser = $current_user;
  }

  /**
   * Check access for the given entity and operation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for.
   * @param string $operation
   *   The requested operation, such as "view", "update" or "delete".
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that requests the operation on the entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkEntityAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    // Target entity types are content entities only.
    if (!($entity instanceof ContentEntityInterface)) {
      return AccessResult::neutral();
    }

    $target = $entity;
    $target_type = $target->getEntityType();
    $cacheability = new CacheableMetadata();
    $etm = $this->entityTypeManager;

    /** @var \Drupal\user\UserInterface $subject */
    if (!($subject = $etm->getStorage('user')->load($account->id()))) {
      // When the subject has no stored entity, one is created on runtime here,
      // and then lookup whether an access record type exists for the target.
      // If so, always revoke access, because matching records may only be valid
      // for existing user entities.
      $subject = $etm->getStorage('user')->create([
        'uid' => $account->id(),
        'status' => 0,
        'name' => '',
      ]);
      return AccessRecordType::loadForAccessCheck($subject, $target_type->id(), $operation, $cacheability, FALSE) ? AccessResult::forbidden()->addCacheableDependency($cacheability) : AccessResult::neutral()->addCacheableDependency($cacheability);
    }

    // Either no configured access record type exists for this type of entity,
    // or the user is an admin and thus has access anyway.
    if ($ar_types = AccessRecordType::loadForAccessCheck($subject, $target_type->id(), $operation, $cacheability)) {
      // As we are now aware of existing access record types that belong to this
      // type of target entity, we need grant or revoke access in accordance of
      // existing access records.
      // First, make sure that a proper version of Entity API is installed.
      // Otherwise, access control does not work at all.
      assert(class_exists('Drupal\entity\QueryAccess\QueryAccessEvent'));

      // Second, check access check by querying for existing access records.
      $exists = FALSE;

      foreach ($ar_types as $ar_type) {
        $query = AccessRecordQueryBuilder::get()->queryByType($ar_type, $subject, $target, $operation);
        if ($exists = (count($query->range(0, 1)->execute()) > 0)) {
          break;
        }
      }

      if ($exists) {
        return AccessResult::allowed()->addCacheableDependency($cacheability);
      }
      return AccessResult::forbidden("No access record exists for the given subject and target.")
        ->addCacheableDependency($cacheability);
    }

    return AccessResult::neutral("No access record type exists for the given type of target.")->addCacheableDependency($cacheability);
  }

  /**
   * Check access for creating an entity.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account that wants to create an entity.
   * @param array $context
   *   Additional context. Must at least contain the entity type ID with the
   *   key "entity_type_id".
   * @param string $entity_bundle
   *   The bundle of entity to create. Use the entity type ID if this type of
   *   entity has no bundles.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkEntityCreateAccess(AccountInterface $account, array $context, $entity_bundle): AccessResultInterface {
    if (!isset($context['entity_type_id'])) {
      // Cannot do anything without knowing the entity type.
      return AccessResult::neutral();
    }

    $etm = $this->entityTypeManager;
    $entity_type_id = $context['entity_type_id'];
    unset($context['entity_type_id']);
    $entity_type = $etm->getDefinition($entity_type_id);

    // Target entity types are content entities only.
    if (!$entity_type->entityClassImplements(ContentEntityInterface::class)) {
      return AccessResult::neutral();
    }

    $values = [];
    if ($entity_type->hasKey('bundle')) {
      $values[$entity_type->getKey('bundle')] = $entity_bundle;
    }
    $keys = $entity_type->getKeys();
    foreach ($context as $k => $v) {
      if ($i = array_search($k, $keys, TRUE)) {
        $values[$i] = $v;
        unset($context[$k]);
      }
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $etm->getStorage($entity_type_id)->create($values);

    foreach ($context as $k => $v) {
      if ($entity->hasField($k)) {
        $entity->$k->setValue($v);
        unset($context[$k]);
      }
    }

    return $this->checkEntityAccess($entity, 'create', $account);
  }

  /**
   * Check access for the given entity field.
   *
   * @param mixed $operation
   *   The requested operation.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition.
   * @param \Drupal\Core\Session\AccountInterface
   *   The account that requests access.
   * @param \Drupal\Core\Field\FieldItemListInterface|null
   *   (optional) The field item list.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   *
   * @throws \InvalidArgumentException
   *   When the target type ID does not match with the field definition.
   */
  public function checkEntityFieldAccess($operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if (!$items) {
      return AccessResult::neutral("Access Records can only determine access with a defined target.");
    }

    /** @var \Drupal\Core\Entity\ContentEntityInterface $target */
    $target = $items ? $items->getEntity() : NULL;
    // Targets are content entities only.
    if (!($target instanceof ContentEntityInterface)) {
      return AccessResult::neutral();
    }

    $field_name = $field_definition->getName();
    $etm = $this->entityTypeManager;
    $target_type_id = $field_definition->getTargetEntityTypeId();
    $target_type = $etm->getDefinition($target_type_id);

    if ($target_type_id !== $target->getEntityTypeId()) {
      throw new \InvalidArgumentException("The entity type ID of the field definition must be the same as the given entity of the field item list.");
    }

    if ($operation === 'edit') {
      $operation = 'update';
    }
    if (!$operation) {
      return AccessResult::neutral("Access Records can only determine access with a defined operation.");
    }

    $cacheability = new CacheableMetadata();

    /** @var \Drupal\user\UserInterface $subject */
    if (!($subject = $etm->getStorage('user')->load($account->id()))) {
      // When the subject has no stored entity, one is created on runtime here,
      // and then lookup whether an access record type exists for the target.
      // If so, always revoke access, because matching records may only be valid
      // for existing user entities.
      $subject = $etm->getStorage('user')->create([
        'uid' => $account->id(),
        'status' => 0,
        'name' => '',
      ]);
      return AccessRecordType::loadForFieldAccessCheck($subject, $target_type->id(), $operation, $cacheability, FALSE) ? AccessResult::forbidden()->addCacheableDependency($cacheability) : AccessResult::neutral()->addCacheableDependency($cacheability);
    }

    $ar_types = AccessRecordType::loadForFieldAccessCheck($subject, $target_type->id(), $operation, $cacheability, TRUE);

    if (!$ar_types) {
      return AccessResult::neutral("No access record type exists with enabled field access.")->addCacheableDependency($cacheability);
    }

    $ar_types = array_filter($ar_types, static function ($ar_type) use ($field_name) {
      /** @var \Drupal\access_records\AccessRecordTypeInterface $ar_type */
      return in_array($field_name, $ar_type->getFieldAccessFieldsAllowed(), TRUE);
    });

    if (!$ar_types) {
      return AccessResult::forbidden("No access record type exists that is able to grant access to the target field.")->addCacheableDependency($cacheability);
    }

    // As we are now aware of existing access record types that belong to the
    // target field, we need grant or revoke access in accordance of
    // existing access records.
    // First, make sure that a proper version of Entity API is installed.
    // Otherwise, access control does not work at all.
    assert(class_exists('Drupal\entity\QueryAccess\QueryAccessEvent'));

    // Second, check access check by querying for existing access records.
    $exists = FALSE;

    foreach ($ar_types as $ar_type) {
      $query = AccessRecordQueryBuilder::get()->queryByType($ar_type, $subject, $target, $operation);
      if ($exists = (count($query->range(0, 1)->execute()) > 0)) {
        break;
      }
    }

    if ($exists) {
      return AccessResult::allowed()->addCacheableDependency($cacheability);
    }
    return AccessResult::forbidden("No access record exists for the given subject and target field.")
      ->addCacheableDependency($cacheability);
  }

}
