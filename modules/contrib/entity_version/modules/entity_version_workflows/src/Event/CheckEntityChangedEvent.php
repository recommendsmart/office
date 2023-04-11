<?php

declare(strict_types = 1);

namespace Drupal\entity_version_workflows\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched by EntityVersionWorkflowManager::isEntityChanged().
 *
 * Allows to alter the list of fields that should be skipped when checking an
 * entity for value changes.
 */
class CheckEntityChangedEvent extends Event {

  const EVENT = 'entity_version_worfklows.check_entity_changed_event';

  /**
   * The array of field names to skip.
   *
   * @var array
   */
  protected $fieldBlacklist;

  /**
   * Get the array of blacklisted fields.
   *
   * @return array
   *   Return the array of blacklisted fields.
   */
  public function getFieldBlacklist(): array {
    return $this->fieldBlacklist;
  }

  /**
   * Set the black listed fields.
   *
   * @param array $field_blacklist
   *   The black listed field names.
   */
  public function setFieldBlacklist(array $field_blacklist): void {
    $this->fieldBlacklist = $field_blacklist;
  }

}
