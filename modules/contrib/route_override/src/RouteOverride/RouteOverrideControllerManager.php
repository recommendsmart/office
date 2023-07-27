<?php

namespace Drupal\route_override\RouteOverride;

use Drupal\route_override\Interfaces\RouteOverrideControllerInterface;
use Drupal\route_override\Routing\RouteSubscriber;
use Drupal\route_override\Traits\ThrowMethodTrait;
use Symfony\Component\Routing\Route;

/**
 * Route override manager.
 *
 * @internal
 */
final class RouteOverrideControllerManager {

  use ThrowMethodTrait;

  /**
   * Route override controllers by priority and ID.
   *
   * @var RouteOverrideControllerInterface[][]
   */
  protected array $controllersByPriority = [];

  /**
   * Route overrides by ID, sorted by priority..
   *
   * @var RouteOverrideControllerInterface[]
   */
  protected array $sortedControllers;

  public function __construct() {}

  public function addRouteOverrideController(RouteOverrideControllerInterface $routeOverrideController, string $id, $priority = 0): void {
    // @see \Drupal\Core\DependencyInjection\Compiler\TaggedHandlersPass::processServiceCollectorPass
    if (isset($this->sortedControllers)) {
      throw new \LogicException('Attempt to add after sorting.');
    }
    if (!\is_int($priority)) {
      throw new \LogicException('Priority must be an integer..');
    }
    $this->controllersByPriority[$priority][$id] = $routeOverrideController;
  }

  /**
   * Get all sorted route override controllers.
   *
   * Any of these controllers can contribute multiple override routes.
   * Sorting is done late, as controllers need a bootstrapped Drupal.
   *
   * @return \Drupal\route_override\Interfaces\RouteOverrideControllerInterface[]
   *   Controllers by service ID.
   */
  public function getSortedRouteOverrideControllers(): array {
    if (!isset($this->sortedControllers)) {
      krsort($this->controllersByPriority, SORT_NUMERIC);
      $this->sortedControllers = array_merge(...$this->controllersByPriority);
    }
    return $this->sortedControllers;
  }

  public function getRouteOverrideControllerForId(string $serviceId): RouteOverrideControllerInterface {
    return $this->getSortedRouteOverrideControllers()[$serviceId] ??
      $this->throw(new \LogicException("Service '$serviceId' not found."));
  }

  public function getRouteOverrideControllerForOverrideRoute(Route $overridingRoute): RouteOverrideControllerInterface {
    $serviceId = RouteSubscriber::extractOverrideControllerServiceId($overridingRoute)
      ?? self::throw(new \LogicException('Route lacks override service id.'));
    return $this->getRouteOverrideControllerForId($serviceId);
  }

}
