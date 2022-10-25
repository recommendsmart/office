<?php

namespace Drupal\field_suggestion\Entity;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\field_suggestion\Controller\FieldSuggestionController;

/**
 * Provides routes for field suggestions.
 */
class FieldSuggestionRouteProvider extends AdminHtmlRouteProvider {

  /**
   * {@inheritdoc}
   */
  protected function getEditFormRoute(EntityTypeInterface $entity_type) {
    return parent::getEditFormRoute($entity_type)->setDefault(
      '_title_callback',
      FieldSuggestionController::class . '::title'
    );
  }

}
