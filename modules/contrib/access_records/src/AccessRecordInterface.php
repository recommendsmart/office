<?php

namespace Drupal\access_records;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\UserInterface;

/**
 * Interface for an access record entity.
 */
interface AccessRecordInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, RevisionLogInterface, EntityPublishedInterface {

  /**
   * Whether the access record is enabled or not.
   *
   * @return bool
   *   Returns TRUE when enabled, FALSE otherwise.
   */
  public function isEnabled(): bool;

  /**
   * Gets the access record's creation timestamp.
   *
   * @return int
   *   Creation timestamp of the access record.
   */
  public function getCreatedTime(): int;

  /**
   * Sets the access record's creation timestamp.
   *
   * @param int $timestamp
   *   The access record creation timestamp.
   *
   * @return \Drupal\access_records\AccessRecordInterface
   *   The called access record entity.
   */
  public function setCreatedTime($timestamp): AccessRecordInterface;

  /**
   * Get a brief string representation of this access record.
   *
   * The returned string has a maximum length of 255 characters.
   * Warning: This might expose undesired field content.
   *
   * This method is not implemented as __toString(). Instead it is this method
   * name, to guarantee compatibility with future changes of the Entity API.
   * Another reason is, that this method is kind of a last resort for generating
   * the access record label, and is not supposed to be used for other purposes
   * like serialization.
   *
   * Modules may implement hook_access_record_get_string_representation() to
   * change the final result, which will be returned by this method.
   *
   * @return string
   *   The string representation of this access record.
   */
  public function getStringRepresentation(): string;

  /**
   * Applies a label pattern to update the label property.
   *
   * Developers may define a custom label pattern by setting a public
   * "label_pattern" as string property or field. If it is not set, then the
   * configured label pattern in the corresponding type config will be used.
   */
  public function applyLabelPattern(): void;

  /**
   * Get the according access record type (i.e. the bundle as object).
   *
   * @return \Drupal\access_records\AccessRecordTypeInterface
   *   The access record type as object.
   */
  public function getType(): AccessRecordTypeInterface;

  /**
   * Get all attached fields that refer to the subject.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   An array of field item lists,
   *   keyed by field name as found within the subject.
   */
  public function getSubjectFields(): array;

  /**
   * Get all attached fields that refer to the target.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface[]
   *   An array of field item lists,
   *   keyed by field name as found within the target.
   */
  public function getTargetFields(): array;

  /**
   * Get the user who last changed this access record.
   *
   * @return \Drupal\user\UserInterface|null
   *   The user who last changed this record, or NULL if not available.
   */
  public function getChangedBy(): ?UserInterface;

}
