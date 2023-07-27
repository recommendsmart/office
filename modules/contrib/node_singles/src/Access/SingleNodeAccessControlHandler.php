<?php

namespace Drupal\node_singles\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeAccessControlHandler;
use Drupal\node\NodeTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Disallows nodes from being deleted, cloned or manually created.
 */
class SingleNodeAccessControlHandler extends NodeAccessControlHandler {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The node singles service.
   *
   * @var \Drupal\node_singles\Service\NodeSinglesInterface
   */
  protected $singles;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->singles = $container->get('node_singles');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function access(EntityInterface $entity, $operation, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $account = $this->prepareUser($account);
    $nodeType = $entity->type->entity;
    $isSingle = FALSE;

    if ($nodeType instanceof NodeTypeInterface) {
      $isSingle = $this->singles->isSingle($nodeType);
    }

    if ($isSingle && $operation === 'delete' && !$account->hasPermission('administer node singles')) {
      $result = AccessResult::forbidden('Singles cannot be deleted manually.')->cachePerPermissions();

      return $return_as_object ? $result : $result->isAllowed();
    }

    if ($isSingle && $operation === 'clone') {
      $result = AccessResult::forbidden('Singles cannot be cloned.')->cachePerPermissions();

      return $return_as_object ? $result : $result->isAllowed();
    }

    $result = parent::access($entity, $operation, $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function createAccess($entity_bundle = NULL, ?AccountInterface $account = NULL, array $context = [], $return_as_object = FALSE) {
    $nodeType = $this->entityTypeManager
      ->getStorage('node_type')
      ->load($entity_bundle);

    if ($this->singles->isSingle($nodeType)) {
      $result = AccessResult::forbidden('Singles can only be created once, automatically');

      return $return_as_object ? $result : $result->isAllowed();
    }

    return parent::createAccess($entity_bundle, $account, $context, $return_as_object);
  }

}
