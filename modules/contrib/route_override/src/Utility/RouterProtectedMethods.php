<?php

namespace Drupal\route_override\Utility;

use Drupal\Core\Routing\EnhancerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Provide access to protected router methods.
 *
 * Methods:
 * - applyRouteEnhancers
 * - matchCollection
 *
 * Copied from \Drupal\Core\Routing\Router
 * Can not use UrlMatch, it lacks RouteEnhancers.
 * Subclassing router generates circular service dependencies.
 *
 * @see \Drupal\Core\Routing\Router
 * @see \Drupal\route_override\Utility\EarlyRouteMatchProvider
 *
 * @internal
 * 
 * @todo Find a better way to access these methods upstream.
 */
class RouterProtectedMethods {

  public const REQUIREMENT_MATCH = 0;
  public const REQUIREMENT_MISMATCH = 1;
  public const ROUTE_MATCH = 2;

  /**
   * Collects HTTP methods that would be allowed for the request.
   */
  protected $allow = [];

  /**
   * The list of available enhancers.
   *
   * Set in ::doMatchCollection()
   *
   * @var \Drupal\Core\Routing\EnhancerInterface[]
   */
  protected $enhancers = [];

  /**
   * Unused.
   *
   * @var \Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface[]
   */
  protected $expressionLanguageProviders = [];
  protected $expressionLanguage;

  public function addRouteEnhancer(EnhancerInterface $route_enhancer) {
    $this->enhancers[] = $route_enhancer;
  }

  public function applyRouteEnhancers(array $defaults, Request $request) {
    foreach ($this->enhancers as $enhancer) {
      $defaults = $enhancer->enhance($defaults, $request);
    }

    return $defaults;
  }

  public function matchCollection(string $pathinfo, Request $request, RouteCollection $routes) {
    // Try a case-sensitive match.
    $match = $this->doMatchCollection($pathinfo, $request, $routes, TRUE);
    // Try a case-insensitive match.
    if ($match === NULL && $routes->count() > 0) {
      $match = $this->doMatchCollection($pathinfo, $request, $routes, FALSE);
    }
    return $match;
  }

  /**
   * DoMatchCollection, copied and replaced $pathinfo with request parameter.
   *
   * The request context was a property taken from final match.
   * Dunno if we need it, no problem to do it this way.
   * Also, symfony brought some ExpressionLanguage handling with it.
   * Not used here, but easier to keep in.
   */
  protected function doMatchCollection(string $pathInfo, Request $request, RouteCollection $routes, $case_sensitive) {
    // @see \Drupal\Core\Routing\UrlMatcher::finalMatch
    $requestContext = (new RequestContext())->fromRequest($request);
    foreach ($routes as $name => $route) {
      $compiledRoute = $route->compile();

      // Set the regex to use UTF-8.
      $regex = $compiledRoute->getRegex() . 'u';
      if (!$case_sensitive) {
        $regex = $regex . 'i';
      }
      if (!preg_match($regex, $pathInfo, $matches)) {
        continue;
      }

      $hostMatches = [];
      if ($compiledRoute->getHostRegex() && !preg_match($compiledRoute->getHostRegex(), $requestContext->getHost(), $hostMatches)) {
        $routes->remove($name);
        continue;
      }

      // Check HTTP method requirement.
      if ($requiredMethods = $route->getMethods()) {
        // HEAD and GET are equivalent as per RFC.
        if ('HEAD' === $method = $requestContext->getMethod()) {
          $method = 'GET';
        }

        if (!in_array($method, $requiredMethods)) {
          $this->allow = array_merge($this->allow, $requiredMethods);
          $routes->remove($name);
          continue;
        }
      }

      $status = $this->handleRouteRequirements($request, $name, $route);

      if (self::ROUTE_MATCH === $status[0]) {
        return $status[1];
      }

      if (self::REQUIREMENT_MISMATCH === $status[0]) {
        $routes->remove($name);
        continue;
      }

      return $this->getAttributes($route, $name, array_replace($matches, $hostMatches));
    }
  }

  /**
   * Copied from symfony and replace parameter $pathinfo with $request.
   * Probably not used anyway, in lack of ExpressionLanguage.
   */
  protected function handleRouteRequirements(Request $request, $name, Route $route)
  {
    $requestContext = (new RequestContext())->fromRequest($request);
    // expression condition
    /** @noinspection PhpUndefinedMethodInspection */
    if ($route->getCondition() && !$this->getExpressionLanguage()->evaluate($route->getCondition(), ['context' => $requestContext, 'request' => $request])) {
      return [self::REQUIREMENT_MISMATCH, null];
    }

    return [self::REQUIREMENT_MATCH, null];
  }

  protected function getExpressionLanguage()
  {
    if (null === $this->expressionLanguage) {
      if (!class_exists(ExpressionLanguage::class)) {
        throw new \LogicException('Unable to use expressions as the Symfony ExpressionLanguage component is not installed.');
      }
      $this->expressionLanguage = new ExpressionLanguage(null, $this->expressionLanguageProviders);
    }

    return $this->expressionLanguage;
  }

  /** @noinspection PhpUndefinedMethodInspection */
  protected function getAttributes(Route $route, $name, array $attributes): array {
    if ($route instanceof RouteObjectInterface && is_string($route->getRouteKey())) {
      $name = $route->getRouteKey();
    }
    $attributes[RouteObjectInterface::ROUTE_NAME] = $name;
    $attributes[RouteObjectInterface::ROUTE_OBJECT] = $route;

    return $this->mergeDefaults($attributes, $route->getDefaults());
  }

  protected function mergeDefaults($params, $defaults)
  {
    foreach ($params as $key => $value) {
      if (!\is_int($key) && null !== $value) {
        $defaults[$key] = $value;
      }
    }

    return $defaults;
  }

}
