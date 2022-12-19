<?php

namespace Drupal\field_bundle\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for field bundle translation routes.
 */
class FieldBundleTranslationRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.field_bundle.content_translation_overview')) {
      $route->setDefault('_controller', '\Drupal\field_bundle\Controller\FieldBundleTranslationController::overview');
    }
    if ($route = $collection->get('entity.field_bundle.content_translation_add')) {
      $route->setDefault('_controller', '\Drupal\field_bundle\Controller\FieldBundleTranslationController::add');
    }
    if ($route = $collection->get('entity.field_bundle.content_translation_edit')) {
      $route->setDefault('_controller', '\Drupal\field_bundle\Controller\FieldBundleTranslationController::edit');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    // Should run after ContentTranslationRouteSubscriber.
    // Therefore priority -220.
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', -220];
    return $events;
  }

}
