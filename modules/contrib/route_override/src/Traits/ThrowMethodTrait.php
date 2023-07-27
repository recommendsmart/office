<?php

namespace Drupal\route_override\Traits;

/**
 * Provides a throw method, obsolete in PHP8 by throw as expression.
 *
 * Provide static method, so can be used both statically and in instance.
 *
 * @internal
 */
trait ThrowMethodTrait {

  protected static function throw(\Exception $exception) {
    throw $exception;
  }

}
