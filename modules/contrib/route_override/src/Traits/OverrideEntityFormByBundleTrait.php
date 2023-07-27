<?php

namespace Drupal\route_override\Traits;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\CacheableTypes\CacheableBool;
use Symfony\Component\Routing\Route;

/**
 * Override entity form by bundle.
 *
 * @api
 *
 * Note: Nothing to do here concerning ::appliesToRouteMatch.
 *   We do not know where config is stored.
 *   Best we can do: provide ::getMaybeBundleConfigOfEntity for convenience.
 */
trait OverrideEntityFormByBundleTrait {

  use OverrideEntityFormTrait;

  protected function appliesToRouteOfEntityForm(EntityTypeInterface $entityType, Route $route): CacheableBool {
    // Does the entity type have a bundle entity?
    if ($bundleEntityTypeId = $entityType->getBundleEntityType()) {
      /** @noinspection PhpUnhandledExceptionInspection */
      $bundleEntityType = $this->entityTypeManager->getDefinition($bundleEntityTypeId);
      assert(!empty($bundleEntityType));
      assert($bundleEntityType instanceof ConfigEntityTypeInterface);

      // Iterate bundles.
      /** @noinspection PhpUnhandledExceptionInspection */
      $bundleConfigStorage = $this->entityTypeManager->getStorage($bundleEntityTypeId);
      foreach ($bundleConfigStorage->loadMultiple() as $bundleConfig) {
        assert($bundleConfig instanceof ConfigEntityInterface);
        $bundleAppliesResult = $this->appliesToRouteOfEntityFormOfBundle($bundleConfig, $entityType, $route);
        if ($bundleAppliesResult->value()) {
          // Shortcut-or, so cacheability of applying bundle is enough.
          return $bundleAppliesResult;
        }
      }
      // No match, so any change of bundle config can affect the result.
      return CacheableBool::create(FALSE, $this->getListCacheabilityOfAppliesToRoute($entityType));
    }
    else {
      // No bundle entity, so not our business and will never be.
      return CacheableBool::create(FALSE, new CacheableMetadata());
    }
  }

  /**
   * Check bundle. Callee is responsible to add single item cacheability, but
   * not list cacheability.
   * @param \Drupal\Core\Entity\EntityTypeInterface $entityType
   */
  abstract protected function appliesToRouteOfEntityFormOfBundle(ConfigEntityInterface $bundleConfig, EntityTypeInterface $entityType, Route $route): CacheableBool;

  abstract protected function getListCacheabilityOfAppliesToRoute(EntityTypeInterface $entityType): CacheableDependencyInterface;

  protected function getMaybeBundleConfigOfEntity(EntityInterface $entity): ?ConfigEntityInterface {
    $entityType = $entity->getEntityType();
    $bundleName = $entity->bundle();
    return $this->getMaybeBundleConfigOfEntityTypeAndBundle($entityType, $bundleName);
  }

  protected function getMaybeBundleConfigOfEntityTypeAndBundle(EntityTypeInterface $entityType, string $bundleName) {
    $bundleEntityTypeId = $entityType->getBundleEntityType();

    // The response for an entity depends on our TPS in its bundle entity.
    if ($bundleEntityTypeId) {
      /** @noinspection PhpUnhandledExceptionInspection */
      $storage = $this->entityTypeManager->getStorage($bundleEntityTypeId);
      $bundleConfig = $storage->load($bundleName);
      // Bundle types are always config entities.
      assert($bundleConfig instanceof ConfigEntityInterface || $bundleConfig === NULL);
      return $bundleConfig;
    }
    return NULL;
  }

}
