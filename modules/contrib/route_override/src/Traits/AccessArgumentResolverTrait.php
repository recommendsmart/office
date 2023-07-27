<?php


namespace Drupal\route_override\Traits;

use Drupal\Core\Access\AccessArgumentsResolverFactoryInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller argument resolver trait.
 *
 * @api
 *
 * It seems Drupal has 2 ArgumentResolver architectures.
 * This is for @access_arguments_resolver_factory
 * used in @access_manager and @access_check.custom.
 *
 * @todo Research why this alternative arguments resolver mechanism was invented.
 *
 * @see \Drupal\Component\Utility\ArgumentsResolver::getArguments(callable $callable)
 * @see \Drupal\Component\Utility\ArgumentsResolverInterface
 * @see \Drupal\Core\Access\AccessArgumentsResolverFactory::getArgumentsResolver(RouteMatchInterface $route_match, AccountInterface $account, Request $request = NULL)
 * @see \Drupal\Core\Access\AccessArgumentsResolverFactoryInterface
 * @see \Drupal\Core\Access\AccessManager
 * @see \Drupal\Core\Access\CustomAccessCheck
 *
 * @see \Drupal\route_override\Traits\ControllerArgumentResolverTrait
 */
trait AccessArgumentResolverTrait {

  protected AccessArgumentsResolverFactoryInterface $accessArgumentResolverFactory;

  protected static function injectAccessArgumentsResolverFactory(self $instance, ContainerInterface $container) {
    /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
    $instance->accessArgumentResolverFactory = $container->get('access_arguments_resolver_factory');
  }

  protected function callWithArgumentsResolved(callable $callable, RouteMatchInterface $route_match, AccountInterface $account, Request $request = NULL) {
    $argumentsResolver = $this->accessArgumentResolverFactory->getArgumentsResolver($route_match, $account, $request);
    $arguments = $argumentsResolver->getArguments($callable);
    return $callable($arguments);
  }

}
