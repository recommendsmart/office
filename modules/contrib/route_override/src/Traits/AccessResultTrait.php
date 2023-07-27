<?php

namespace Drupal\route_override\Traits;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\CacheableTypes\CacheableBool;
use Symfony\Component\HttpFoundation\Request;

/**
 * Access result resolver trait.
 *
 * @api
 */
trait AccessResultTrait {

  abstract protected function boolAccess(RouteMatchInterface $route_match, AccountInterface $account, Request $request = NULL): CacheableBool;

  public function access(RouteMatchInterface $routeMatch, AccountInterface $account, Request $request = NULL): AccessResultInterface {
    $boolAccess = $this->boolAccess($routeMatch, $account, $request);
    return AccessResult::allowedIf($boolAccess->value())
      ->addCacheableDependency($boolAccess);
  }

}
