<?php

namespace Drupal\route_override\Traits;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\CacheableTypes\CacheableBool;
use Drupal\route_override\Routing\RouteSubscriber;
use Symfony\Component\Routing\Route;

/**
 * Override entity forms.
 *
 * @api
 */
trait OverrideEntityFormTrait {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function appliesToRoute(Route $route): CacheableBool {
    if ($entityType = $this->extractMaybeEntityTypeFromRoute($route)) {
      return $this->appliesToRouteOfEntityForm($entityType, $route);
    }
    // No entity form, so not our business and never will be: No cacheability.
    return CacheableBool::create(FALSE, new CacheableMetadata());
  }

  abstract protected function appliesToRouteOfEntityForm(EntityTypeInterface $entityType, Route $route): CacheableBool;


  protected function extractMaybeEntityTypeFromRoute(Route $route): ?EntityTypeInterface {
    if ($entityFormSpec = RouteSubscriber::getEntityFormSpec($route)) {
      // Form type be 'default' (create) or 'edit', or any other form mode.
      // If it's 'delete', we sort it out by entity access below.
      [$entityTypeId, $formType] = explode('.', $entityFormSpec, 2);
      // Assume _entity_form spec is valid.
      assert(!empty($entityTypeId));
      /** @noinspection PhpUnhandledExceptionInspection */
      $entityType = $this->entityTypeManager->getDefinition($entityTypeId);
      // Assume _entity_form spec refers to valid entity type.
      assert(!empty($entityType));
      return $entityType;
    }
    else {
      return NULL;
    }
  }

  protected function extractEntityFromRouteMatchOfEntityForm(RouteMatchInterface $route_match): EntityInterface {
    // Is this the route of an entity form?
    // @see \Drupal\Core\Entity\Routing\EntityRouteProviderInterface
    // @see \Drupal\Core\Entity\EntityResolverManager::setParametersFromEntityInformation
    // @see \Drupal\Core\Entity\HtmlEntityFormController::getFormObject
    $formArg = RouteSubscriber::getEntityFormSpec($route_match->getRouteObject());
    // Use default form mode if no other was appended.
    $formArg .= '.default';
    [$entityTypeId, $operation] = explode('.', $formArg);
    $formObject = $this->entityTypeManager->getFormObject($entityTypeId, $operation);
    /** @noinspection PhpUnnecessaryLocalVariableInspection */
    $entity = $formObject->getEntityFromRouteMatch($route_match, $entityTypeId);
    return $entity;
  }

  protected function isEntityDeleteFormRoute(Route $route, string $entityTypeId): CacheableBool {
    $entityAccessSpec = $route->getRequirement('_entity_access');
    return CacheableBool::create(
      $entityAccessSpec === "{$entityTypeId}.delete",
      // The route will never change.
      new CacheableMetadata()
    );
  }

  protected function isEntityCreateFormRoute(Route $route, string $entityTypeId): CacheableBool {
    // Don't rely on form mode naming, rather inspect route parameters.
    // @see \Drupal\Core\Entity\EntityResolverManager::setParametersFromEntityInformation
    $routeHasEntityParameter = strpos($route->getPath(), '{' . $entityTypeId . '}') !== FALSE;
    // Depends on route, result only used in route and rendering that
    // depends on route anyway, so no cacheability necessary.
    return CacheableBool::create(
      !$routeHasEntityParameter,
      // The route will never change.
      new CacheableMetadata()
    );
  }

}
