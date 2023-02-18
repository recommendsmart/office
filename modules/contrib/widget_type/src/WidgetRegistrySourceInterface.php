<?php

namespace Drupal\widget_type;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface defining a widget registry source entity type.
 */
interface WidgetRegistrySourceInterface extends ConfigEntityInterface {

  /**
   * Calculates a pair of dark and light colors for this source.
   *
   * This is used to easily identify widgets coming from a source.
   *
   * @return array{0: string, 1: string}
   *   The hex representation of the colors.
   */
  public function calculateColors(): array;

}
