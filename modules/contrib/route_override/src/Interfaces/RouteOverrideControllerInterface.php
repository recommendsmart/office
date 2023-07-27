<?php

namespace Drupal\route_override\Interfaces;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\CacheableTypes\CacheableBool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

interface RouteOverrideControllerInterface extends ControllerInterface {

  public function appliesToRoute(Route $route): CacheableBool;

  public function appliesToRouteMatch(RouteMatchInterface $routeMatch, Request $request): CacheableBool;

}
