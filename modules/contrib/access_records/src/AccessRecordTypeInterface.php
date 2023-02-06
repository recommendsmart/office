<?php

namespace Drupal\access_records;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityDescriptionInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;

/**
 * Interface for an access record type.
 */
interface AccessRecordTypeInterface extends ConfigEntityInterface, RevisionableEntityBundleInterface, EntityDescriptionInterface {

  /**
   * Get the default status for access records of this type.
   *
   * @return bool
   *   The default status value.
   */
  public function getStatus(): bool;

  /**
   * Set the default status value.
   *
   * @param bool $status
   *   The default status value.
   *
   * @return $this
   */
  public function setStatus($status): AccessRecordTypeInterface;

  /**
   * Get the pattern to use for creating the label of the access record.
   *
   * @return string
   *   The label pattern.
   */
  public function getLabelPattern(): string;

  /**
   * Set the pattern to use for creating the label of the access record.
   *
   * @param string $pattern
   *   The label pattern to set.
   *
   * @return $this
   */
  public function setLabelPattern($pattern): AccessRecordTypeInterface;

  /**
   * Get the entity type of the subject.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The subject entity type, or NULL if not defined.
   */
  public function getSubjectType(): ?EntityTypeInterface;

  /**
   * Get the subject entity type ID.
   *
   * @return string
   *   The entity type ID, or NULL if not defined.
   */
  public function getSubjectTypeId(): ?string;

  /**
   * Set the subject entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID of the subject.
   *
   * @return $this
   */
  public function setSubjectTypeId(string $entity_type_id): AccessRecordTypeInterface;

  /**
   * Get the entity type of the target.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The target entity type, or NULL if not defined.
   */
  public function getTargetType(): ?EntityTypeInterface;

  /**
   * Get the target entity type ID.
   *
   * @return string
   *   The entity type ID, or NULL if not defined.
   */
  public function getTargetTypeId(): ?string;

  /**
   * Set the target entity type ID.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   *
   * @return $this
   */
  public function setTargetTypeId(string $entity_type_id): AccessRecordTypeInterface;

  /**
   * Get the allowed operations.
   *
   * @return string[]
   *   The list of allowed operations, e.g. ['view', 'update', 'delete'].
   */
  public function getOperations(): array;

  /**
   * Set the allowed operations.
   *
   * @param array $operations
   *   The list of allowed operations to set.
   *
   * @return $this
   */
  public function setOperations(array $operations): AccessRecordTypeInterface;

  /**
   * Get the names of the attached fields that refer to the subject.
   *
   * @return string[]
   *   The list of matching field names, keyed by field names within the
   *   access record, whereas values are the field name on the subject.
   */
  public function getSubjectFieldNames(): array;

  /**
   * Get the names of the attached fields that refer to the target.
   *
   * @return string[]
   *   The list of matching field names, keyed by field names within the
   *   access record, whereas values are the field name on the target.
   */
  public function getTargetFieldNames(): array;

  /**
   * Adds a set of default fields to all access records of this type.
   *
   * Invokation of this method usually happens right after the access record
   * type was added for the first time.
   *
   * @return $this
   */
  public function addDefaultFields(): AccessRecordTypeInterface;

  /**
   * Whether field access is additionally enabled.
   *
   * @return bool
   *   Returns TRUE if field access is enabled, FALSE otherwise.
   */
  public function getFieldAccessEnabled(): bool;

  /**
   * Enable or disable additional field access.
   *
   * @param bool $enabled
   *   (optional) Set to TRUE to enable field access, FALSE to disable it.
   *   Default is TRUE for enabling field access.
   *
   * @return $this
   */
  public function setFieldAccessEnabled(bool $enabled = TRUE): AccessRecordTypeInterface;

  /**
   * Returns a list of allowed fields to access.
   *
   * This is only relevant when field access is enabled.
   * @see self::getFieldAccessEnabled().
   *
   * @return string[]
   *   The allowed fields as array, where its values are field machine names.
   */
  public function getFieldAccessFieldsAllowed(): array;

  /**
   * Set the allowed fields to access.
   *
   * @param string[] $field_names
   *   The allowed fields as array, where its values are field machine names.
   *
   * @return $this
   */
  public function setFieldAccessFieldsAllowed(array $field_names): AccessRecordTypeInterface;

}
