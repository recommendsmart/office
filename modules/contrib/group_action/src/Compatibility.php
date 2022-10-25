<?php

namespace Drupal\group_action;

use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Contains workarounds to improve compatibility with other modules.
 *
 * @internal
 *   This class is not meant to be used as a public API. It is subject for name
 *   change or may be removed completely, also on minor version updates.
 */
class Compatibility {

  /**
   * The recursion threshold of the ECA module.
   *
   * Is set to FALSE if the ECA module is not installed.
   *
   * @var int|bool|null
   */
  static private $ecaRecursionThreshold = NULL;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a new Compatibility object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * Performs necessary steps before executing a group-related operation.
   */
  public function beforeOperation(): void {
    // The ECA processor is automatically detecting recursion, and stops
    // its execution chain when its parameterized threshold got reached.
    // This is a problem when group content is being added or removed from
    // a group, because the group module automatically saves the content entity
    // (again) in order to clear access cache and update access policies.
    // This section therefore raises the threshold by one level for ECA, and
    // makes sure to reset to the default recursion level once it finished
    // the group operation.
    if (self::$ecaRecursionThreshold === NULL) {
      if ($this->moduleHandler->moduleExists('eca') && ($processor = self::getEcaProcessor())) {
        $threshold = NULL;
        \Closure::fromCallable(function () use (&$threshold) {
          $threshold = $this->recursionThreshold ?? NULL;
        })->call($processor);
        static::$ecaRecursionThreshold = $threshold ?? FALSE;
        unset($threshold);
      }
      else {
        self::$ecaRecursionThreshold = FALSE;
      }
    }
    if (($threshold = self::$ecaRecursionThreshold) === 1) {
      \Closure::fromCallable(function () use ($threshold) {
        $this->recursionThreshold = $threshold + 1;
      })->call(self::getEcaProcessor());
    }
  }

  /**
   * Performs necessary steps after a group-related operation was finished.
   */
  public function afterOperation(): void {
    if (($threshold = self::$ecaRecursionThreshold) === 1) {
      \Closure::fromCallable(function () use ($threshold) {
        $this->recursionThreshold = $threshold;
      })->call(self::getEcaProcessor());
    }
  }

  /**
   * Get the ECA processor.
   *
   * @return \Drupal\eca\Processor|null
   *   The processor, or NULL if not available.
   */
  static protected function getEcaProcessor() {
    return \Drupal::hasService('eca.processor') ? \Drupal::service('eca.processor') : NULL;
  }

}
