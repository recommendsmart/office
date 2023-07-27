<?php

declare(strict_types=1);
namespace Drupal\route_override;

use Symfony\Component\Routing\Route;

final class RouteOverride {

  protected const ROUTE_OPTION_ROUTE_NAME = 'route_override_route_name';

  protected const ROUTE_OPTION_ORIGINAL_ROUTE_NAME = 'route_override_original';

  protected const ROUTE_OPTION_OVERRIDE_SERVICE_ID = 'route_override_service_id';

  public static function setRouteName(Route $route, string $routeName): void {
    $route->setOption(self::ROUTE_OPTION_ROUTE_NAME, $routeName);
  }
  
  public static function getRouteName(Route $route): ?string {
    return $route->getOption(self::ROUTE_OPTION_ROUTE_NAME);
  }
  
  public static function setOriginalRouteName(Route $route, string $originalRouteName): void {
    $route->setOption(self::ROUTE_OPTION_ORIGINAL_ROUTE_NAME, $originalRouteName);
  }
  
  public static function getOriginalRouteName(Route $route): ?string {
    return $route->getOption(self::ROUTE_OPTION_ORIGINAL_ROUTE_NAME);
  }
  
  public static function setOverrideServiceId(Route $route, string $overrideServiceId): void {
    $route->setOption(self::ROUTE_OPTION_OVERRIDE_SERVICE_ID, $overrideServiceId);
  }
  
  public static function getOverrideServiceId(Route $route): ?string {
    return $route->getOption(self::ROUTE_OPTION_OVERRIDE_SERVICE_ID);
  }
  
}
