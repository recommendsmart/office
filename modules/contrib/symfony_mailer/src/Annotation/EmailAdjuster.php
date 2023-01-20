<?php

namespace Drupal\symfony_mailer\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an EmailAdjuster item annotation object.
 *
 * @Annotation
 */
class EmailAdjuster extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var string
   *
   * @ingroup plugin_translatable
   */
  public $label;

}
