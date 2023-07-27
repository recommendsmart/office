<?php

namespace Drupal\route_override\Routing;

use Drupal\Core\Routing\FilterInterface;
use Drupal\route_override\RouteOverride\RouteOverrideControllerManager;
use Drupal\route_override\Traits\ThrowMethodTrait;
use Drupal\route_override\Utility\EarlyRouteMatchProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Filter routes.
 *
 * @internal
 *
 * @see \Drupal\Core\Routing\Router::matchRequest
 */
final class RouteFilter implements FilterInterface {

  use ThrowMethodTrait;

  protected RouteOverrideControllerManager $routeOverrideControllerManager;

  protected EarlyRouteMatchProvider $routeMatchProvider;

  public function __construct(RouteOverrideControllerManager $routeOverrideControllerManager, EarlyRouteMatchProvider $routeMatchProvider) {
    $this->routeOverrideControllerManager = $routeOverrideControllerManager;
    $this->routeMatchProvider = $routeMatchProvider;
  }

  public function filter(RouteCollection $collection, Request $request): RouteCollection {
    $originalRoutes = $this->extractOriginalRoutes($collection);
    // No idea if the case of multiple original routes is realistic, but no
    // problem to handle the general case anyway.
    foreach ($originalRoutes as $originalRouteName => $originalRoute) {
      $sortedOverridingRoutes = $this->getSortedOverridingRoutes($collection, $originalRouteName);
      $sortedOverridingRouteNames = array_keys($sortedOverridingRoutes->all());
      $foundApplyingOverride = FALSE;
      $collectedOverrideServiceIds = [];
      foreach ($sortedOverridingRoutes as $overridingRouteName => $overridingRoute) {
        $collectedOverrideServiceIds[] = RouteSubscriber::extractOverrideControllerServiceId($overridingRoute);
        RouteSubscriber::verifyCacheability($overridingRoute, $collectedOverrideServiceIds);

        $overrideService = $this->routeOverrideControllerManager->getRouteOverrideControllerForOverrideRoute($overridingRoute);

        $routeMatch = $this->routeMatchProvider->createRouteMatch($request, $overridingRoute, $overridingRouteName);
        $foundApplyingOverride = $overrideService->appliesToRouteMatch($routeMatch, $request)->value();
        if ($foundApplyingOverride) {
          // Found an applying override for this original route (only).
          // Remove the other.
          $collection->remove($originalRouteName);
          $otherOverridingRouteNames = array_diff($sortedOverridingRouteNames, [$overridingRouteName]);
          $collection->remove($otherOverridingRouteNames);
          // Do not check other overrides for this route.
          break;
        }
      }
      if (!$foundApplyingOverride) {
        // Remove all override routes.
        // This ensures that for each original route, now exactly one is left.
        $collection->remove($sortedOverridingRouteNames);
      }
    }
    return $collection;
  }

  private function extractOriginalRoutes(RouteCollection $collection): RouteCollection {
    $originalRoutes = new RouteCollection();
    foreach ($collection as $routeName => $route) {
      if ($originalRouteName = RouteSubscriber::extractOriginalRouteName($route)) {
        $originalRoute = $collection->get($originalRouteName);
        if (!$originalRoute) {
          throw new \LogicException("Can not find original route '$originalRouteName' for override '$routeName'.");
        }
        $originalRoutes->add($originalRouteName, $originalRoute);
      }
    }
    return $originalRoutes;
  }

  /**
   * Get override routes for original, sorted by controller priority.
   *
   * Code is optimized for auditability.
   */
  private function getSortedOverridingRoutes(RouteCollection $collection, string $originalRouteName): RouteCollection {
    $overridingRoutesByRouteName = array_filter($collection->all(),
      fn(Route $route) => RouteSubscriber::extractOriginalRouteName($route) === $originalRouteName);
    $overrideControllerServiceIdsByOverridingRouteName = array_map(
      fn(Route $route) => RouteSubscriber::extractOverrideControllerServiceId($route) ?? $this->throw(new \LogicException(sprintf('Inconsistent route: %s', var_export($route, TRUE)))),
      $overridingRoutesByRouteName
    );

    // Compare arrays loosely, as array_unique may change ordering.
    if ($overrideControllerServiceIdsByOverridingRouteName != array_unique($overrideControllerServiceIdsByOverridingRouteName)) {
      throw new \LogicException(sprintf('One controller overrides the same original route multiple times: %s', var_export($overrideControllerServiceIdsByOverridingRouteName, TRUE)));
    }
    // The above ensures that nothing is lost here.
    $overrideRouteNamesByOverrideControllerServiceId = array_flip($overrideControllerServiceIdsByOverridingRouteName);

    // Sorted by controller priority.
    $allSortedOverrideControllersByServiceId = $this->routeOverrideControllerManager->getSortedRouteOverrideControllers();
    $sortedOverrideControllersByServiceId = array_intersect_key($allSortedOverrideControllersByServiceId, $overrideRouteNamesByOverrideControllerServiceId);
    assert(array_keys($sortedOverrideControllersByServiceId) == array_keys($overrideRouteNamesByOverrideControllerServiceId));
    $sortedOverrideControllerServiceIds = array_keys($sortedOverrideControllersByServiceId);
    $sortedOverridingRouteNames = array_map(
      function(string $serviceId) use ($collection, $overrideRouteNamesByOverrideControllerServiceId) {
        return $overrideRouteNamesByOverrideControllerServiceId[$serviceId] ?? $this->throw(new \LogicException("Not found: $serviceId. Available: " . implode('|', array_keys($overrideRouteNamesByOverrideControllerServiceId))));
      },
      $sortedOverrideControllerServiceIds
    );

    $sortedOverridingRouteCollection = new RouteCollection();
    foreach ($sortedOverridingRouteNames as $sortedOverridingRouteName) {
      $sortedOverridingRouteCollection->add($sortedOverridingRouteName, $collection->get($sortedOverridingRouteName));
    }
    return $sortedOverridingRouteCollection;
  }

}
