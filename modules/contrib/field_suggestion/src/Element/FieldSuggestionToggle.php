<?php

namespace Drupal\field_suggestion\Element;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Class FieldSuggestionToggle.
 *
 * @package Drupal\field_suggestion\Element
 */
class FieldSuggestionToggle implements TrustedCallbackInterface {

  /**
   * #pre_render callback to associate the appropriate toggle.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element.
   *
   * @return array
   *   The modified element with all group members.
   */
  public static function preRender(array $element) {
    $element['#attributes']['type'] = 'button';
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['preRender'];
  }

}
