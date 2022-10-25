<?php

namespace Drupal\field_suggestion;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;

/**
 * Provides an interface defining a field suggestion entity.
 */
interface FieldSuggestionInterface extends ContentEntityInterface, EntityPublishedInterface {

  /**
   * Gets a related entity type.
   *
   * @return string
   *   The entity type identifier.
   */
  public function type();

  /**
   * Gets a related field.
   *
   * @return string
   *   The field name.
   */
  public function field();

  /**
   * Check if it's an ignored entity.
   *
   * @return bool
   *   TRUE, if so.
   */
  public function isIgnored();

  /**
   * Mark a suggestion as ignored or pinned.
   *
   * @param bool $ignored
   *   The suggestion type will be the following:
   *   - TRUE: The ignored suggestion.
   *   - FALSE: The pinned suggestion.
   *
   * @return $this
   */
  public function setIgnored(bool $ignored);

  /**
   * Check if it has excluded entities.
   *
   * @return bool
   *   TRUE, if so.
   */
  public function hasExcluded();

  /**
   * Returns set of excluded entities.
   *
   * @return array
   *   The entities list.
   */
  public function getExcluded();

  /**
   * Gets the amount of excluded entities.
   *
   * @return int
   *   The number.
   */
  public function countExcluded();

  /**
   * Check if a suggestion can be used only one time.
   *
   * @return bool
   *   TRUE, if so.
   */
  public function isOnce();

  /**
   * Mark a suggestion as one that can only be used one time.
   *
   * @return $this
   */
  public function setOnce();

  /**
   * Gets value.
   *
   * @param bool $original
   *   (optional) TRUE if the identifier of referenced entity should not be
   *   replaced by a label. Defaults to FALSE.
   *
   * @return string
   *   The suggestion.
   */
  public function label($original = FALSE);

}
