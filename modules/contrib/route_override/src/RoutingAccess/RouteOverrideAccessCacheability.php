<?php

namespace Drupal\route_override\RoutingAccess;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\CacheableTypes\CacheableBool;
use Drupal\route_override\RouteOverride\RouteOverrideControllerManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * An access check that only adds cacheability.
 *
 * @internal
 */
final class RouteOverrideAccessCacheability implements AccessInterface {

  const KEY = '_route_override_cacheability';

  protected RouteOverrideControllerManager $routeOverrideControllerManager;

  public function __construct(RouteOverrideControllerManager $routeOverrideControllerManager) {
    $this->routeOverrideControllerManager = $routeOverrideControllerManager;
  }

  /**
   * Add route override cacheability.
   *
   * Whether overrides for an original route apply or not, the final result must
   * carry the cacheability of $overrideController->applies(...).
   *
   * @see \Drupal\route_override\Routing\RouteSubscriber::alterRoutes
   */
  public function access(RouteMatchInterface $route_match, Request $request) {
    $route = $route_match->getRouteObject();
    $serviceIdsRequirement = $route->getRequirements()[static::KEY] ?? NULL;
    if (!\is_string($serviceIdsRequirement)) {
      throw new \LogicException('Need array of service IDs');
    }
    $serviceIds = explode('+', $serviceIdsRequirement);

    $cacheability = new CacheableMetadata();
    foreach ($serviceIds as $serviceId) {
      $overrideController = $this->routeOverrideControllerManager->getRouteOverrideControllerForId($serviceId);
      $cacheability->addCacheableDependency($overrideController->appliesToRouteMatch($route_match, $request));
    }
    return CacheableBool::create(TRUE, $cacheability);
  }

}
