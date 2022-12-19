<?php

namespace Drupal\access_records\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for access record translation routes.
 */
class AccessRecordTranslationRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('entity.access_record.content_translation_overview')) {
      $route->setDefault('_controller', '\Drupal\access_records\Controller\AccessRecordTranslationController::overview');
    }
    if ($route = $collection->get('entity.access_record.content_translation_add')) {
      $route->setDefault('_controller', '\Drupal\access_records\Controller\AccessRecordTranslationController::add');
    }
    if ($route = $collection->get('entity.access_record.content_translation_edit')) {
      $route->setDefault('_controller', '\Drupal\access_records\Controller\AccessRecordTranslationController::edit');
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
