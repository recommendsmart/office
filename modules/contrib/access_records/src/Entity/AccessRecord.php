<?php

namespace Drupal\access_records\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\access_records\AccessRecordInterface;
use Drupal\access_records\AccessRecordTypeInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;

/**
 * Defines access records as content entities.
 *
 * @ContentEntityType(
 *   id = "access_record",
 *   label = @Translation("Access record"),
 *   label_collection = @Translation("Access records"),
 *   bundle_label = @Translation("Access record type"),
 *   label_singular = @Translation("access record"),
 *   label_plural = @Translation("access records"),
 *   label_count = @PluralTranslation(
 *     singular = "@count access record",
 *     plural = "@count access records",
 *   ),
 *   handlers = {
 *     "view_builder" = "Drupal\Core\Entity\EntityViewBuilder",
 *     "list_builder" = "Drupal\access_records\AccessRecordListBuilder",
 *     "access" = "Drupal\access_records\AccessRecordAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\access_records\Form\AccessRecordForm",
 *       "edit" = "Drupal\access_records\Form\AccessRecordForm",
 *       "delete" = "Drupal\access_records\Form\AccessRecordDeleteForm",
 *       "default" = "Drupal\access_records\Form\AccessRecordForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   base_table = "access_record",
 *   data_table = "access_record_data",
 *   revision_table = "access_record_revision",
 *   revision_data_table = "access_record_revision_data",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer access_record",
 *   entity_keys = {
 *     "id" = "ar_id",
 *     "revision" = "ar_vid",
 *     "langcode" = "ar_langcode",
 *     "default_langcode" = "ar_default_langcode",
 *     "bundle" = "ar_type",
 *     "label" = "ar_label",
 *     "uuid" = "ar_uuid",
 *     "status" = "ar_enabled",
 *     "published" = "ar_enabled",
 *     "uid" = "ar_uid",
 *     "owner" = "ar_uid",
 *     "revision_translation_affected" = "ar_rev_trans_affected",
 *     "weight" = "ar_weight"
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "ar_rev_uid",
 *     "revision_created" = "ar_rev_timestamp",
 *     "revision_log_message" = "ar_rev_log"
 *   },
 *   links = {
 *     "canonical" = "/access-record/{access_record}",
 *     "add-form" = "/access-record/add/{access_record_type}",
 *     "add-page" = "/access-record/add",
 *     "edit-form" = "/access-record/{access_record}/edit",
 *     "delete-form" = "/access-record/{access_record}/delete",
 *     "collection" = "/admin/content/access-record"
 *   },
 *   bundle_entity_type = "access_record_type",
 *   field_ui_base_route = "entity.access_record_type.edit_form",
 *   common_reference_target = FALSE,
 *   permission_granularity = "bundle",
 *   token_type = "access_record"
 * )
 */
class AccessRecord extends EditorialContentEntityBase implements AccessRecordInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $time = \Drupal::time()->getCurrentTime();
    $values += [
      'ar_created' => $time,
      'ar_changed' => $time,
      'ar_uid' => static::getDefaultEntityOwner(),
      'ar_changed_uid' => \Drupal::currentUser()->id(),
    ];
    if (isset($values['ar_label']) && $values['ar_label'] !== '') {
      // Disable the label pattern when a label is already there.
      $values['label_pattern'] = '';
    }
    /** @var \Drupal\access_records\AccessRecordTypeInterface $access_record_type */
    $access_record_type = isset($values['ar_type']) ? \Drupal::entityTypeManager()->getStorage('access_record_type')->load($values['ar_type']) : NULL;
    if ($access_record_type) {
      if (!isset($values['ar_subject_type'])) {
        $values['ar_subject_type'] = $access_record_type->getSubjectTypeId();
      }
      if (!isset($values['ar_target_type'])) {
        $values['ar_target_type'] = $access_record_type->getTargetTypeId();
      }
      if (!isset($values['ar_operation'])) {
        $values['ar_operation'] = $access_record_type->getOperations();
      }
      if (!isset($values['ar_enabled'])) {
        $values['ar_enabled'] = $access_record_type->getStatus();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->get('ar_label')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    return $this->isPublished();
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    $this->set('ar_changed_uid', \Drupal::currentUser()->id());

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the admin user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(1);
      }
    }

    // If no revision owner has been set explicitly, make the record owner the
    // revision owner.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }

    $this->applyLabelPattern();
  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateTagsOnSave($update) {
    parent::invalidateTagsOnSave($update);
    Cache::invalidateTags($this->getRelatedCacheTagsToInvalidate());
  }

  /**
   * {@inheritdoc}
   */
  protected static function invalidateTagsOnDelete(EntityTypeInterface $entity_type, array $entities) {
    parent::invalidateTagsOnDelete($entity_type, $entities);
    $tags = [];
    /** @var \Drupal\access_records\Entity\AccessRecord $entity */
    foreach ($entities as $entity) {
      $related_tags = $entity->getRelatedCacheTagsToInvalidate();
      $tags = array_merge($tags, array_combine($related_tags, $related_tags));
    }
    Cache::invalidateTags(array_unique($tags));
  }

  /**
   * Get cache tags that belong to related data of this access record.
   */
  public function getRelatedCacheTagsToInvalidate(): array {
    $type = $this->getType();
    return [
      $type->getSubjectTypeId() . '_list',
      $type->getTargetTypeId() . '_list',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime(): int {
    return $this->get('ar_created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp): AccessRecordInterface {
    $this->set('ar_created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedTime() {
    return $this->get('ar_changed')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setChangedTime($timestamp) {
    $this->set('ar_changed', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getStringRepresentation(): string {
    $string = '';
    \Drupal::moduleHandler()->invokeAllWith('access_record_get_string_representation', function (callable $hook, string $module) use (&$string) {
      $string = $hook($this, $string);
    });

    if (trim($string) === '') {
      $string = $this->generateFallbackStringRepresentation();
    }

    if (mb_strlen($string) > 255) {
      $string = Unicode::truncate($string, 255, TRUE, TRUE, 20);
    }

    return $string;
  }

  /**
   * Implements the magic __toString() method.
   *
   * When a string representation is explicitly needed, consider directly using
   * ::getStringRepresentation() instead.
   */
  public function __toString() {
    return $this->getStringRepresentation();
  }

  /**
   * {@inheritdoc}
   */
  public function applyLabelPattern(): void {
    if (isset($this->label_pattern)) {
      $label_pattern = $this->hasField('label_pattern') ? $this->get('label_pattern')->getString() : $this->label_pattern;
    }
    elseif ($type_id = $this->bundle()) {
      /** @var \Drupal\access_records\AccessRecordTypeInterface $type */
      if ($type = \Drupal::entityTypeManager()->getStorage('access_record_type')->load($type_id)) {
        $label_pattern = $type->getLabelPattern();
      }
    }
    if (!empty($label_pattern)) {
      $string = (string) \Drupal::token()->replace($label_pattern, ['access_record' => $this], [
        'langcode' => $this->language()->getId(),
        'clear' => TRUE,
      ]);
      if (mb_strlen($string) > 255) {
        $string = Unicode::truncate($string, 255, TRUE, TRUE, 20);
      }
      $this->ar_label->value = $string;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): AccessRecordTypeInterface {
    return \Drupal::entityTypeManager()->getStorage('access_record_type')->load($this->bundle());
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['ar_uid']
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setDescription(t('The user ID of the record author.'))
      ->setSetting('target_type', 'user')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the record was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the record was last edited.'));

    $fields['ar_changed_uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Changed by'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getDefaultEntityOwner')
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_enabled']
      ->setLabel(t('Enabled'))
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => ['display_label' => TRUE],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setRequired(FALSE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_subject_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Subject type'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_target_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target type'))
      ->setRequired(TRUE)
      ->setTranslatable(FALSE)
      ->setRevisionable(FALSE)
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['ar_operation'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Allowed operations'))
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('allowed_values_function', 'Drupal\access_records\Entity\AccessRecord::availableOperations')
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * Fallback method for generating a string representation.
   *
   * @see ::getStringRepresentation()
   *
   * @return string
   *   The fallback value for the string representation.
   */
  protected function generateFallbackStringRepresentation() {
    $components = \Drupal::service('entity_display.repository')->getFormDisplay('access_record', $this->bundle())->getComponents();

    // The label is available in the form, thus the user is supposed to enter
    // a value for it. For this case, use the label directly and return it.
    if (!empty($components['ar_label'])) {
      return $this->label();
    }

    $access_record_type = $this->getType();
    $etm = \Drupal::entityTypeManager();
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $efm */
    $efm = \Drupal::service('entity_field.manager');

    $subject_type = $etm->getDefinition($this->ar_subject_type->value);
    $target_type = $etm->getDefinition($this->ar_target_type->value);
    $subject_fields = $efm->getFieldStorageDefinitions($subject_type->id());
    $target_fields = $efm->getFieldStorageDefinitions($target_type->id());

    $operations_available = static::availableOperations();
    $langcode = $this->language()->getId();
    $operations = [];
    foreach ($this->get('ar_operation') as $item) {
      if (empty($item->value)) {
        continue;
      }
      if (isset($operations_available[$item->value])) {
        $operations[] = t($operations_available[$item->value]->getUntranslatedString(), [], ['langcode' => $langcode]);
      }
    }

    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

    $subject_values = [];
    $target_values = [];
    $multiple_subject_values = FALSE;
    $multiple_target_values = FALSE;
    $subject_field_names = $access_record_type->getSubjectFieldNames();
    $target_field_names = $access_record_type->getTargetFieldNames();
    foreach (array_keys($components) as $field_name) {
      // Components can be extra fields, check if the field really exists.
      if (!$this->hasField($field_name)) {
        continue;
      }

      if (isset($subject_field_names[$field_name]) || isset($target_field_names[$field_name])) {
        $items = $this->get($field_name);
        $field_definition = $items->getFieldDefinition()->getFieldStorageDefinition();
        $is_config_entity = FALSE;
        if ($field_definition->getType() === 'entity_reference' && ($reference_type_id = $field_definition->getSetting('target_type'))) {
          $reference_type = $etm->getDefinition($reference_type_id);
          $is_config_entity = !($reference_type->entityClassImplements(ContentEntityInterface::class));
        }
        $property_names = $field_definition->getPropertyNames();
        $main_property = $field_definition->getMainPropertyName() ?: '_NONE';
        if (empty($property_names)) {
          $property_names = ['_NONE'];
        }
        $field_values = [];
        $multiple_values = FALSE;
        foreach ($property_names as $property) {
          $values = [];
          if ($is_config_entity && $property === $main_property) {
            /** @var \Drupal\Core\Field\EntityReferenceFieldItemListInterface $items */
            foreach ($items->referencedEntities() as $config) {
              $values[] = $config->label() ?: $config->id();
            }
          }
          else {
            /** @var \Drupal\Core\Field\FieldItemInterface $item */
            foreach ($items as $item) {
              if ($item instanceof BooleanItem) {
                $values[] = $item->getPossibleOptions()[$item->value];
              }
              elseif ($property === '_NONE') {
                $values[] = $item->getString();
              }
              elseif (isset($item->$property) && is_scalar($item->$property)) {
                $values[] = $item->$property;
              }
              elseif (!$item->get($property)->getDataDefinition()->isComputed()) {
                $values[] = $item->get($property)->getString();
              }
            }
          }
          $num = count($values);
          if ($num > 1) {
            $multiple_values = TRUE;
          }
          if ($num > 3) {
            $values = array_slice($values, 0, 3);
            $values[4] = t('+ @num more', ['@num' => $num - 3], ['langcode' => $langcode]);
          }
          if (!empty($values)) {
            $args = [
              '@property' => $property,
              '@values' => implode(', ', $values),
            ];
            $field_values[] = $property === $main_property ? t('@values', $args, ['langcode' => $langcode]) : t('@property @values', $args, ['langcode' => $langcode]);
          }
        }
        $num = count($field_values);
        if ($num) {
          if ($num > 1) {
            $multiple_values = TRUE;
          }
          if (isset($subject_field_names[$field_name])) {
            $multiple_subject_values = $multiple_subject_values || $multiple_values;
            $label = $subject_fields[$subject_field_names[$field_name]]->getLabel();
            if ($label instanceof TranslatableMarkup) {
              $label = $label->getUntranslatedString();
            }
            $args = [
              '@field' => t($label, [], ['langcode' => $langcode]),
              '@values' => implode(', ', $field_values),
            ];
            $subject_values[$field_name] = t('@field @values', $args, ['langcode' => $langcode]);
          }
          if (isset($target_field_names[$field_name])) {
            $multiple_target_values = $multiple_target_values || $multiple_values;
            $label = $target_fields[$target_field_names[$field_name]]->getLabel();
            if ($label instanceof TranslatableMarkup) {
              $label = $label->getUntranslatedString();
            }
            $args = [
              '@field' => t($label, [], ['langcode' => $langcode]),
              '@values' => implode(', ', $field_values),
            ];
            $target_values[$field_name] = t('@field @values', $args, ['langcode' => $langcode]);
          }
        }
      }
    }

    $args = [
      '@subject' => t($subject_type->getLabel()->getUntranslatedString(), [], ['langcode' => $langcode]),
      '@target' => t($target_type->getLabel()->getUntranslatedString(), [], ['langcode' => $langcode]),
      '@operations' => implode(' and ', $operations),
      '@subject_having' => $multiple_subject_values ? t('having one of', [], ['langcode' => $langcode]) : t('having', [], ['langcode' => $langcode]),
      '@target_having' => $multiple_target_values ? t('having one of', [], ['langcode' => $langcode]) : t('having', [], ['langcode' => $langcode]),
      '@subject_values' => empty($subject_values) ? t('(undefined - never matches)', [], ['langcode' => $langcode]) : implode(' ' . t('or') . ' ', $subject_values),
      '@target_values' => empty($target_values) ? t('(undefined - never matches)', [], ['langcode' => $langcode]) : implode(' ' . t('or') . ' ', $target_values),
    ];

    return t('Any @subject @subject_having @subject_values can @operations any @target @target_having @target_values', $args, ['langcode' => $langcode]);
  }

  /**
   * {@inheritdoc}
   */
  public function getSubjectFields(): array {
    $fields = [];
    foreach ($this->getType()->getSubjectFieldNames() as $ar_field_name => $field_name) {
      $fields[$field_name] = $this->get($ar_field_name);
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFields(): array {
    $fields = [];
    foreach ($this->getType()->getTargetFieldNames() as $ar_field_name => $field_name) {
      $fields[$field_name] = $this->get($ar_field_name);
    }
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getChangedBy(): ?UserInterface {
    if (isset($this->ar_changed_uid, $this->ar_changed_uid->target_id)) {
      return \Drupal::entityTypeManager()->getStorage('user')->load($this->ar_changed_uid->target_id);
    }
    return NULL;
  }

  /**
   * Returns a list of available operations, suitable for Field and Form API.
   *
   * @return array
   *   Available operations, keyed by operation, values are translatable labels.
   */
  public static function availableOperations(): array {
    $available = [
      'create' => t('Create'),
      'view' => t('View'),
      'update' => t('Update'),
      'delete' => t('Delete'),
    ];
    \Drupal::moduleHandler()->alter('access_record_operations', $available);
    return $available;
  }

}
