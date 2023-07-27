<?php

namespace Drupal\route_override\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\route_override\RouteOverride;
use Drupal\route_override\RouteOverride\RouteOverrideControllerManager;
use Drupal\route_override\RoutingAccess\RouteOverrideAccessCacheability;
use Drupal\route_override\RoutingCacheability\RouteCacheability;
use Drupal\route_override\Traits\ThrowMethodTrait;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * @internal
 */
final class RouteSubscriber extends RouteSubscriberBase {

  use ThrowMethodTrait;

  protected RouteOverrideControllerManager $routeOverrideControllerManager;

  public function __construct(RouteOverrideControllerManager $routeOverrideControllerManager) {
    $this->routeOverrideControllerManager = $routeOverrideControllerManager;
  }

  public static function extractOriginalRouteName(Route $route): ?string {
    // @todo Deprecate in favor of called method.
    return RouteOverride::getOriginalRouteName($route);
  }

  public static function extractOverrideControllerServiceId(Route $route): ?string {
    // @todo Deprecate in favor of called method.
    return RouteOverride::getOverrideServiceId($route);
  }

  /**
   * Alter routes.
   *
   * Add routes like
   * - route_override.entity_form_hijack.entity_form_controller_override._.node.add
   * - route_override.group_mandatory.entity_form_controller_override._.node.add
   * Set requirements
   * - _route_override_custom_access = $overrideService::access
   * - _route_override_cacheability = implode('+', $collectedOverrideServiceIds)
   * Set options
   * - route_override_original
   * - route_override_service_id
   *
   * ::getSortedControllersByOriginalRouteName
   * Set options
   * - route_override.cacheability.tags
   * - route_override.cacheability.contexts
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $overrideControllersByOriginal = $this->getSortedControllersByOriginalRouteName($collection);
    $collectedOverrideServiceIds = [];
    foreach ($overrideControllersByOriginal as $originalRouteName => $sortedOverrideControllers) {
      $originalRoute = $collection->get($originalRouteName)
        ?? $this->throw(new \LogicException('Invalid route name'));
      foreach ($sortedOverrideControllers as $overrideServiceId => $overrideController) {
        // Collect for cacheability: Any override depends on, if it applies,
        // and if all preceding controllers apply. The original is only applied
        // if no override applies, so depends on all.
        $collectedOverrideServiceIds[] = $overrideServiceId;

        // https://www.drupal.org/docs/drupal-apis/routing-system/structure-of-routes
        $overrideRoute = clone $originalRoute;
        // This overrides all _entity_form etc. Unset it anyway.
        // @see \Drupal\Core\Entity\Enhancer\EntityRouteEnhancer::enhance
        $overrideRoute->setDefault('_controller', "{$overrideServiceId}:build");
        $this->unsetEntityDefaults($overrideRoute);

        // Remove all access checks.
        $overrideRoute->setRequirements([]);
        // @see \Drupal\Core\Access\CustomAccessCheck::access
        $overrideRoute->setRequirement('_route_override_custom_access', "{$overrideServiceId}:access");
        // @see \Drupal\route_override\RoutingAccess\RouteOverrideAccessCacheability::access
        $overrideRoute->setRequirement(RouteOverrideAccessCacheability::KEY, implode('+', $collectedOverrideServiceIds));

        // @see \Drupal\route_override\RouteOverrideController
        RouteOverride::setOriginalRouteName($overrideRoute, $originalRouteName);
        RouteOverride::setOverrideServiceId($overrideRoute, $overrideServiceId);

        // This route name can change anytime.
        $overrideRouteName = "route_override.$overrideServiceId._.$originalRouteName";
        $collection->add($overrideRouteName, $overrideRoute);
      }
      // @see \Drupal\route_override\RoutingAccess\RouteOverrideAccessCacheability::access
      $originalRoute->setRequirement(RouteOverrideAccessCacheability::KEY, implode('+', $collectedOverrideServiceIds));
    }
  }

  /**
   * Unset '_entity_form' and friends, but copy if we need them later.
   *
   * @see \Drupal\route_override\Traits\OverrideEntityFormTrait::extractMaybeEntityTypeFromRoute
   * @see \Drupal\route_override\Traits\OverrideEntityFormTrait::extractEntityFromRouteMatchOfEntityForm
   */
  private function unsetEntityDefaults(Route $overrideRoute): void {
    $defaults = $overrideRoute->getDefaults();
    foreach (['_entity_form', '_entity_view', '_entity_list',] as $key) {
      if (isset($defaults[$key])) {
        $defaults["_route_override_{$key}"] = $defaults[$key];
      }
      unset($defaults[$key]);
    }
    $overrideRoute->setDefaults($defaults);
  }

  public static function getEntityFormSpec(Route $route): ?string {
    // This may be called before or after ::unsetEntityDefaults
    return $route->getDefault('_entity_form')
      ?? $route->getDefault('_route_override__entity_form');
  }

  public static function verifyCacheability(Route $route, array $realCacheabilityServiceIds): void {
    $cacheabilitySpec = $route->getRequirement(RouteOverrideAccessCacheability::KEY);
    $declaredCacheabilityServiceIds = explode('+', $cacheabilitySpec);
    if ($diff = array_diff($realCacheabilityServiceIds, $declaredCacheabilityServiceIds)) {
      throw new \LogicException(sprintf('Missing service IDs: %s', implode('+', $diff)));
    }
  }

  /**
   * Get list of priority-sorted controllers by original route name.
   *
   * Route override controllers may override multiple original routes.
   * As they come in sorted by priority, the controller list for any original
   * route is also sorted by priority.
   */
  private function getSortedControllersByOriginalRouteName(RouteCollection $collection): array {
    $presorted = [];
    foreach ($this->routeOverrideControllerManager->getSortedRouteOverrideControllers()
             as $routeOverrideServiceId => $routeOverrideController) {
      foreach ($collection as $routeName => $route) {
        // @todo Deprecate this in favor of ::appliesToRoute($route, $routeName)
        RouteOverride::setRouteName($route, $routeName);
        $applies = $routeOverrideController->appliesToRoute($route);
        if ($applies->value()) {
          $presorted[$routeName][$routeOverrideServiceId] = $routeOverrideController;
        }
        RouteCacheability::add($route, $applies);
      }
    }
    return $presorted;
  }

}
