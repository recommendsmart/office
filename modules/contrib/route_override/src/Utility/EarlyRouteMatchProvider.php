<?php

namespace Drupal\route_override\Utility;

use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Drupal\Core\Routing\RouteMatch;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provide an early route match.
 *
 * The routing system only provides a route match for the final route.
 * To provide good DX for override filtering, we create matches earlier.
 *
 * @see \Drupal\Core\Routing\RouteProvider::getRouteCollectionForRequest
 * @see \Drupal\system\PathBasedBreadcrumbBuilder::getRequestForPath
 * @see \Symfony\Component\HttpKernel\EventListener\RouterListener::onKernelRequest
 * @see \Drupal\Core\Routing\Router::matchRequest
 * @see \Drupal\Core\Routing\CurrentRouteMatch::getRouteMatch
 *
 * @internal
 *
 * @todo Find a better way to do this.
 */
class EarlyRouteMatchProvider {

  protected InboundPathProcessorInterface $pathProcessorManager;

  protected CurrentPathStack $currentPath;

  protected RouterProtectedMethods $routerProtectedMethods;

  public function __construct(InboundPathProcessorInterface $pathProcessorManager, CurrentPathStack $currentPath, RouterProtectedMethods $routerProtectedMethods) {
    $this->pathProcessorManager = $pathProcessorManager;
    $this->currentPath = $currentPath;
    $this->routerProtectedMethods = $routerProtectedMethods;
  }


  public function createRouteMatch(Request $originalRequest, Route $route, string $routeName) {
    // Ensure nothing pollutes the original request.
    $request = clone $originalRequest;

    // Pretend filters only returned this one route.
    $routeCollection = new RouteCollection();
    $routeCollection->add($routeName, clone $route);

    // @see \Drupal\Core\Routing\RouteProvider::getRouteCollectionForRequest
    // $path = $request->getPathInfo();
    // $path = $path === '/' ? $path : rtrim($path, '/');
    // $path = $this->pathProcessorManager->processInbound($path, $request);

    $path = $this->currentPath->getPath($originalRequest);

    $parameters = $this->routerProtectedMethods->matchCollection($path, $request, $routeCollection);
    // @todo Care for no match found.
    $parameters = $this->routerProtectedMethods->applyRouteEnhancers($parameters, $request);

    // Like \Symfony\Component\HttpKernel\EventListener\RouterListener::onKernelRequest
    $request->attributes->add($parameters);
    unset($parameters['_route'], $parameters['_controller']);
    $request->attributes->set('_route_params', $parameters);

    // Route object and name are set in
    // @see \Drupal\Core\Routing\UrlMatcher::getAttributes
    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    $routeMatch = RouteMatch::createFromRequest($request);
    return $routeMatch;
  }

}
