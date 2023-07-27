<?php


namespace Drupal\route_override\Traits;


use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;

/**
 * Controller argument resolver trait.
 *
 * @api
 *
 * It seems Drupal has 2 ArgumentResolver architectures.
 * This is for @http_kernel.controller.argument_resolver,
 * used in @early_rendering_controller_wrapper_subscriber.
 *
 * @see \Drupal\Core\EventSubscriber\EarlyRenderingControllerWrapperSubscriber::onController
 * @see \Symfony\Component\HttpKernel\Controller\ArgumentResolver::getArguments
 * @see \Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface
 * Argument resolvers defined in core.services.yml:
 * @see \Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestAttributeValueResolver
 * @see \Symfony\Component\HttpKernel\Controller\ArgumentResolver\RequestValueResolver
 * @see \Drupal\Core\Controller\ArgumentResolver\Psr7RequestValueResolver
 * @see \Drupal\Core\Controller\ArgumentResolver\RouteMatchValueResolver
 * @see \Symfony\Component\HttpKernel\Controller\ArgumentResolver\DefaultValueResolver
 *
 * The other:
 * @see \Drupal\route_override\Traits\AccessArgumentResolverTrait
 */
trait ControllerArgumentResolverTrait {

  protected ArgumentResolverInterface $controllerArgumentResolver;

  public static function injectControllerArgumentResolver(self $instance, ContainerInterface $container) {
    /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
    $instance->controllerArgumentResolver = $container->get('http_kernel.controller.argument_resolver');
  }

  protected function callWithArgumentsResolved(callable $callable, Request $request) {
    $arguments = $this->controllerArgumentResolver->getArguments($request, $callable);
    return $callable($arguments);
  }

}
