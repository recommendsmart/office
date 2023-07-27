<?php

namespace Drupal\route_override\RoutingCacheability;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Add cacheability metadata (currently only cache tags) to the route collection
 *
 * Only cache tags are supported currently.
 *
 * For cache contexts, there are old approaches in DomainRouteProvider, and
 * newer ones via RouteProvider::addExtraCacheKeyPart, used by
 * WorkspaceRequestSubscriber::onKernelRequest.
 *
 * They seem to have a limitation, not providing full cache context support for
 * routing, only for caching intermediate results.
 * @see \Drupal\Core\Routing\RouteProvider::getRouteCollectionForRequest
 *
 * @internal
 */
final class RoutingCacheabilityRouteSubscriber extends RouteSubscriberBase {

  const FAKE_ROUTE_NAME = 'route_override.cacheability';

  public function __construct() {}

  public static function getSubscribedEvents(): array {
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -999];
    return $events;
  }

  protected function alterRoutes(RouteCollection $collection): RouteCollection {
    $cacheability = $this->collectCacheability($collection);

    if ($cacheability->getCacheMaxAge() !== Cache::PERMANENT) {
      throw new \LogicException('Max-age caching of routing is not allowed.');
    }
    $contexts = $cacheability->getCacheContexts();
    $contexts = array_diff($contexts, ['language']);
    if ($contexts) {
      throw new \LogicException('Cache contexts for routing are not allowed.');
    }

    $this->storeCacheability($collection, $cacheability);
    return $collection;
  }

  public function collectCacheability(RouteCollection $routeCollection): CacheableDependencyInterface {
    $cacheability = new CacheableMetadata();
    foreach ($routeCollection as $routeName =>  $route) {
      $routeCacheability = RouteCacheability::get($route);
      if ($routeCacheability->getCacheMaxAge() !== Cache::PERMANENT) {
        \Drupal::logger('route_override')->notice(sprintf('Route %s has unsupported cache max-age: %s', $routeName, $routeCacheability->getCacheMaxAge()));
      }
      $routeCacheContexts = array_diff($routeCacheability->getCacheContexts(), ['language']);
      if ($routeCacheContexts) {
        \Drupal::logger('route_override')->notice(sprintf('Route %s has unsupported cache context: %s', $routeName, implode('+', $routeCacheContexts)));
      }
      $filteredRouteCacheability = (new CacheableMetadata())
        ->addCacheTags($routeCacheability->getCacheTags());
      $cacheability->addCacheableDependency($filteredRouteCacheability);
    }
    return $cacheability;
  }

  public function storeCacheability(RouteCollection $routeCollection, CacheableDependencyInterface $cacheability) {
    $fakeRoute = $this->createFakeRoute();
    RouteCacheability::add($fakeRoute, $cacheability);
    $routeCollection->add(static::FAKE_ROUTE_NAME, $fakeRoute);
  }

  protected function createFakeRoute() {
    return new Route('/_/_', [], ['_access' => 'FALSE']);
  }

}
