<?php

namespace Drupal\route_override\Traits;

use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\CacheableTypes\CacheableBool;
use Symfony\Component\HttpFoundation\Request;

/**
 * Override entity forms, override config stored in bundle config TPS.
 *
 * @api
 */
trait OverrideEntityFormFromBundleConfigTrait {

  use OverrideEntityFormByBundleTrait;

  protected EntityTypeManagerInterface $entityTypeManager;

  protected function getListCacheabilityOfAppliesToRoute(EntityTypeInterface $entityType): CacheableDependencyInterface {
    $bundleEntityTypeId = $entityType->getBundleEntityType();
    $bundleEntityType = $this->entityTypeManager->getDefinition($bundleEntityTypeId);

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheTags($bundleEntityType->getListCacheTags());
    $cacheability->addCacheContexts($bundleEntityType->getListCacheContexts());
    return $cacheability;
  }

  public function appliesToRouteMatch(RouteMatchInterface $routeMatch, Request $request): CacheableBool {
    $entity = $this->extractEntityFromRouteMatchOfEntityForm($routeMatch);
    $bundleConfig = $this->getMaybeBundleConfigOfEntity($entity);
    // Override was applied only to route of entity type with bundle config.
    // @see \Drupal\route_override\Traits\OverrideEntityFormByBundleTrait::appliesToRouteOfEntityForm
    assert(!empty($bundleConfig));
    return $this->appliesToRouteMatchOfEntityFormOfBundle($entity, $bundleConfig, $routeMatch, $request);
  }

  /**
   * Check if applies. Callee is responsible to add $bundleConfig cacheability.
   */
  abstract protected function appliesToRouteMatchOfEntityFormOfBundle(EntityInterface $entity, ConfigEntityInterface $bundleConfig, RouteMatchInterface $route_match, Request $request): CacheableBool;

}
