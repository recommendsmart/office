<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Provides the interface for the email builder plugin manager.
 */
interface EmailBuilderManagerInterface extends PluginManagerInterface {

  /**
   * Import not yet done, ready to import.
   */
  const IMPORT_READY = 0;

  /**
   * Import complete.
   */
  const IMPORT_COMPLETE = 1;

  /**
   * Import skipped.
   */
  const IMPORT_SKIPPED = 2;

  /**
   * Gets information about config importing.
   *
   * @return array
   *   Array keyed by plugin ID with values as an array with these keys:
   *   - name: A human-readable name for this import operation.
   *   - state: State, one of the IMPORT_ constants.
   *   - state_name: A human-readable name for the state.
   *   - warning: A human-readable warning.
   */
  public function getImportInfo();

  /**
   * Checks if config importing is required.
   *
   * @return bool
   *   TRUE if import is required.
   */
  public function importRequired();

  /**
   * Imports config for the specified id.
   *
   * @param string $id
   *   The plugin ID.
   */
  public function import(string $id);

  /**
   * Imports all config not yet imported.
   */
  public function importAll();

  /**
   * Imports all config not yet imported.
   *
   * @param string $id
   *   The plugin ID.
   * @param int $state
   *   The state, one of the IMPORT_ constants.
   */
  public function setImportState(string $id, int $state);

}
