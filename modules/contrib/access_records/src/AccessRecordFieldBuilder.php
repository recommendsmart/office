<?php

namespace Drupal\access_records;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;

/**
 * Service that helps adding fields to access records.
 */
class AccessRecordFieldBuilder {

  /**
   * Adds a set of default fields to all access records of the given type.
   *
   * @param \Drupal\access_records\AccessRecordTypeInterface $ar_type
   *   The type of all access records the fields should be attached to.
   */
  public function addDefaultFields(AccessRecordTypeInterface $ar_type): void {
    if (!($subject_type = $ar_type->getSubjectType())) {
      return;
    }

    $this->addSubjectReferenceField($ar_type);
    // For users as subjects, add the role reference field.
    if ($subject_type->id() === 'user') {
      $this->addEntityReferenceField($ar_type, 'user_role', 'subject_roles', 'Roles of subjects');
    }

    $create_only = (['create'] === $ar_type->getOperations());

    if (!$create_only) {
      $this->addTargetReferenceField($ar_type);
    }

    if ($target_type = $ar_type->getTargetType()) {
      // Add a bundle field for the target.
      if ($target_type->hasKey('bundle')) {
        if ($target_type->hasKey('bundle') && ($bundle_entity_type = $target_type->getBundleEntityType())) {
          $bundle_label = $target_type->getBundleLabel() ?: $target_type->getLabel() . ' ' . $target_type->getKey('bundle');
          $this->addEntityReferenceField($ar_type, $bundle_entity_type, 'target_' . $target_type->getKey('bundle'), $bundle_label . ' of targets');
        }
      }

      if (!$create_only) {
        $base_fields = $target_type->getClass()::baseFieldDefinitions($target_type);
        if ($target_type->hasKey('published')) {
          $published_key = $target_type->getKey('published');
          if (isset($base_fields[$published_key])) {
            /** @var \Drupal\Core\Field\BaseFieldDefinition $base_field */
            $base_field = $base_fields[$published_key];
            $label = $base_field->getLabel() . ' targets';
            $this->addBooleanField($ar_type, 'target_' . $published_key, $label);
          }
        }
        if ($target_type->hasKey('owner')) {
          // Add a field that matches subject ID to target's owner ID.
          $owner_key = $target_type->getKey('owner');
          if (isset($base_fields[$owner_key])) {
            /** @var \Drupal\Core\Field\BaseFieldDefinition $base_field */
            $base_field = $base_fields[$owner_key];
            $label = 'Targets ' . strtolower($base_field->getLabel());
            $this->addEntityReferenceField($ar_type, $subject_type->id(), 'subject_' . $subject_type->getKey('id') . '__target_' . $owner_key, $label);
          }
        }
      }
    }
  }

  /**
   * Adds a field holding a reference to subjects (mostly users).
   *
   * @param \Drupal\access_records\AccessRecordTypeInterface $ar_type
   *   The type of access records the field should be attached to.
   * @param string|null $label
   *   (optional) The label for the reference field.
   *
   * @return \Drupal\field\Entity\FieldConfig|null
   *   An entity reference field object or NULL if not available.
   */
  public function addSubjectReferenceField(AccessRecordTypeInterface $ar_type, ?string $label = NULL): ?FieldConfig {
    if (!($subject_type_id = $ar_type->getSubjectTypeId())) {
      return NULL;
    }

    $entity_type = $ar_type->getSubjectType();
    $id_key = $entity_type->getKey('id');
    $field_name = 'subject_' . $id_key;
    if (is_null($label)) {
      $label = sprintf("%s subjects", $entity_type->getLabel()->getUntranslatedString());
    }

    return $this->addEntityReferenceField($ar_type, $subject_type_id, $field_name, $label);
  }

  /**
   * Adds a field holding a reference to targets.
   *
   * @param \Drupal\access_records\AccessRecordTypeInterface $ar_type
   *   The type of access records the field should be attached to.
   * @param string|null $label
   *   (optional) The label for the reference field.
   *
   * @return \Drupal\field\Entity\FieldConfig|null
   *   An entity reference field object or NULL if not available.
   */
  public function addTargetReferenceField(AccessRecordTypeInterface $ar_type, ?string $label = NULL): ?FieldConfig {
    if (!($target_type_id = $ar_type->getTargetTypeId())) {
      return NULL;
    }

    $entity_type = $ar_type->getTargetType();
    $id_key = $entity_type->getKey('id');
    $field_name = 'target_' . $id_key;
    if (is_null($label)) {
      $label = sprintf("%s targets", $entity_type->getLabel()->getUntranslatedString());
    }

    return $this->addEntityReferenceField($ar_type, $target_type_id, $field_name, $label);
  }

  /**
   * Adds an entity reference field to all records of the given type.
   *
   * @param \Drupal\access_records\AccessRecordTypeInterface $ar_type
   *   The type of access records the field should be attached to.
   * @param string $entity_type_id
   *   (optional) The entity type ID.
   * @param string $field_name
   *   (optional) The machine name of the reference field.
   * @param string $label
   *   (optional) The human-readable label for the reference field.
   *
   * @return \Drupal\field\Entity\FieldConfig|null
   *   An entity reference field object or NULL if not available.
   */
  public function addEntityReferenceField(AccessRecordTypeInterface $ar_type, string $entity_type_id, string $field_name, string $label): ?FieldConfig {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_type_id);
    $field_storage = FieldStorageConfig::loadByName('access_record', $field_name);
    if (is_null($field_storage)) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'access_record',
        'type' => 'entity_reference',
        'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
        'translatable' => TRUE,
        'settings' => ['target_type' => $entity_type_id],
      ]);
      $field_storage->save();
    }

    $field = FieldConfig::loadByName('access_record', $ar_type->id(), $field_name);
    if (empty($field)) {
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $ar_type->id(),
        'label' => $label,
        'settings' => [
          'handler' => 'default',
          'handler_settings' => [
            'target_bundles' => NULL,
            'auto_create' => FALSE,
          ],
        ],
      ]);
      $field->save();

      /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
      $display_repository = \Drupal::service('entity_display.repository');

      // Assign widget settings for the default form mode.
      if ($entity_type->entityClassImplements(ContentEntityInterface::class)) {
        // Use autocomplete for content entities.
        $display_repository->getFormDisplay('access_record', $ar_type->id())
          ->setComponent($field_name, [
            'type' => 'entity_reference_autocomplete',
            'region' => 'content',
            'settings' => [
              'match_operator' => 'CONTAINS',
              'match_limit' => 10,
              'size' => 60,
              'placeholder' => '',
            ],
          ])
          ->save();
      }
      else {
        // Use checkboxes for config entities.
        $display_repository->getFormDisplay('access_record', $ar_type->id())
          ->setComponent($field_name, [
            'type' => 'options_buttons',
            'region' => 'content',
            'settings' => [],
          ])
          ->save();
      }

      // Assign display settings for the default view mode.
      $display_repository->getViewDisplay('access_record', $ar_type->id())
        ->setComponent($field_name, [
          'label' => 'above',
          'type' => 'entity_reference_label',
          'settings' => ['link' => TRUE],
          'region' => 'content',
        ])
        ->save();
    }

    return $field;
  }

  /**
   * Adds an boolean field to all records of the given type.
   *
   * @param \Drupal\access_records\AccessRecordTypeInterface $ar_type
   *   The type of access records the field should be attached to.
   * @param string $field_name
   *   (optional) The machine name of the field.
   * @param string $label
   *   (optional) The human-readable label for the field.
   *
   * @return \Drupal\field\Entity\FieldConfig|null
   *   A field config object or NULL if not available.
   */
  public function addBooleanField(AccessRecordTypeInterface $ar_type, string $field_name, string $label): ?FieldConfig {
    $field_storage = FieldStorageConfig::loadByName('access_record', $field_name);
    if (is_null($field_storage)) {
      $field_storage = FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'access_record',
        'type' => 'boolean',
        'cardinality' => 1,
        'translatable' => TRUE,
        'settings' => [],
      ]);
      $field_storage->save();
    }

    $field = FieldConfig::loadByName('access_record', $ar_type->id(), $field_name);
    if (empty($field)) {
      $field = FieldConfig::create([
        'field_storage' => $field_storage,
        'bundle' => $ar_type->id(),
        'label' => $label,
        'settings' => [],
      ]);
      $field->save();

      /** @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface $display_repository */
      $display_repository = \Drupal::service('entity_display.repository');

      // Assign widget settings for the default form mode.
      $display_repository->getFormDisplay('access_record', $ar_type->id())
        ->setComponent($field_name, [
          'type' => 'options_buttons',
          'region' => 'content',
          'settings' => [],
        ])
        ->save();

      // Assign display settings for the default view mode.
      $display_repository->getViewDisplay('access_record', $ar_type->id())
        ->setComponent($field_name, [
          'label' => 'above',
          'type' => 'boolean',
          'settings' => [
            'format' => 'yes-no',
            'format_custom_false' => '',
            'format_custom_true' => '',
          ],
          'region' => 'content',
        ])
        ->save();
    }

    return $field;
  }

}
