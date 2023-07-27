<?php

namespace Drupal\storage\Entity;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\field\FieldConfigInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Storage entity.
 *
 * @ingroup storage
 *
 * @ContentEntityType(
 *   id = "storage",
 *   label = @Translation("Storage"),
 *   bundle_label = @Translation("Storage type"),
 *   handlers = {
 *     "storage" = "Drupal\storage\StorageStorage",
 *     "view_builder" = "Drupal\storage\StorageViewBuilder",
 *     "list_builder" = "Drupal\storage\StorageListBuilder",
 *     "views_data" = "Drupal\storage\Entity\StorageViewsData",
 *     "translation" = "Drupal\storage\StorageTranslationHandler",
 *     "form" = {
 *       "default" = "Drupal\storage\Form\StorageForm",
 *       "add" = "Drupal\storage\Form\StorageForm",
 *       "edit" = "Drupal\storage\Form\StorageForm",
 *       "delete" = "Drupal\storage\Form\StorageDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\storage\StorageHtmlRouteProvider",
 *     },
 *     "access" = "Drupal\storage\StorageAccessControlHandler",
 *   },
 *   base_table = "storage",
 *   data_table = "storage_field_data",
 *   revision_table = "storage_revision",
 *   revision_data_table = "storage_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   common_reference_target = TRUE,
 *   permission_granularity = "bundle",
 *   admin_permission = "administer storage entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "uid" = "user_id",
 *     "langcode" = "langcode",
 *     "published" = "status",
 *   },
 *   links = {
 *     "canonical" = "/storage/{storage}",
 *     "add-page" = "/storage/add",
 *     "add-form" = "/storage/add/{storage_type}",
 *     "edit-form" = "/storage/{storage}/edit",
 *     "delete-form" = "/storage/{storage}/delete",
 *     "delete-multiple-form" = "/storage/delete",
 *     "version-history" = "/storage/{storage}/revisions",
 *     "revision" = "/storage/{storage}/revisions/{storage_revision}/view",
 *     "revision_revert" = "/storage/{storage}/revisions/{storage_revision}/revert",
 *     "revision_delete" = "/storage/{storage}/revisions/{storage_revision}/delete",
 *     "translation_revert" = "/storage/{storage}/revisions/{storage_revision}/revert/{langcode}",
 *     "collection" = "/admin/content/storage",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   bundle_entity_type = "storage_type",
 *   field_ui_base_route = "entity.storage_type.edit_form",
 *   fieldable = TRUE
 * )
 */
class Storage extends EditorialContentEntityBase implements StorageInterface {

  use EntityChangedTrait;
  use EntityPublishedTrait;

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    parent::preCreate($storage_controller, $values);
    $values += [
      'user_id' => \Drupal::currentUser()->id(),
    ];
    if (isset($values['name']) && $values['name'] !== '') {
      // Disable the name pattern when a name is already there.
      $values['name_pattern'] = '';
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function urlRouteParameters($rel) {
    $uri_route_parameters = parent::urlRouteParameters($rel);

    if ($rel === 'revision_revert' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }
    elseif ($rel === 'revision_delete' && $this instanceof RevisionableInterface) {
      $uri_route_parameters[$this->getEntityTypeId() . '_revision'] = $this->getRevisionId();
    }

    return $uri_route_parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);

    foreach (array_keys($this->getTranslationLanguages()) as $langcode) {
      $translation = $this->getTranslation($langcode);

      // If no owner has been set explicitly, make the anonymous user the owner.
      if (!$translation->getOwner()) {
        $translation->setOwnerId(0);
      }
    }

    // If no revision author has been set explicitly,
    // make the storage owner the revision author.
    if (!$this->getRevisionUser()) {
      $this->setRevisionUserId($this->getOwnerId());
    }

    $this->applyNamePattern();
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($name) {
    $this->set('name', $name);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner() {
    return $this->get('user_id')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId() {
    return $this->get('user_id')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid) {
    $this->set('user_id', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account) {
    $this->set('user_id', $account->id());
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasLinkTemplate($rel) {
    if ($rel === 'canonical') {
      /** @var \Drupal\storage\Entity\StorageTypeInterface $type */
      $type = \Drupal::entityTypeManager()->getStorage('storage_type')->load($this->bundle());
      if (!$type->hasCanonical()) {
        return FALSE;
      }
    }
    return parent::hasLinkTemplate($rel);
  }

  /**
   * {@inheritdoc}
   */
  public function applyNamePattern() {
    if (isset($this->name_pattern)) {
      $name_pattern = $this->hasField('name_pattern') ? $this->get('name_pattern')->getString() : $this->name_pattern;
    }
    elseif ($config_id = $this->bundle()) {
      /** @var \Drupal\storage\Entity\StorageTypeInterface $config */
      if ($config = \Drupal::entityTypeManager()->getStorage('storage_type')->load($config_id)) {
        $name_pattern = $config->getNamePattern();
      }
    }
    if (!empty($name_pattern)) {
      $string = (string) \Drupal::token()->replacePlain($name_pattern, ['storage' => $this], [
        'langcode' => $this->language()->getId(),
        'clear' => TRUE,
      ]);
      if (mb_strlen($string) > 255) {
        $string = Unicode::truncate($string, 255, TRUE, TRUE, 20);
      }
      $this->name->value = $string;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getStringRepresentation() {
    $string = '';
    \Drupal::moduleHandler()->invokeAllWith('storage_get_string_representation', function (callable $hook, string $module) use (&$string) {
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
   * Fallback method for generating a string representation.
   *
   * @see ::getStringRepresentation()
   *
   * @return string
   *   The fallback value for the string representation.
   */
  protected function generateFallbackStringRepresentation() {
    $components = \Drupal::service('entity_display.repository')->getFormDisplay('storage', $this->bundle())->getComponents();

    // The name is available in the form, thus the user is required to enter
    // a value for it. For this case, use the name directly and return it.
    if (!empty($components['name'])) {
      return $this->label();
    }

    uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');
    $values = [];

    foreach (array_keys($components) as $field_name) {
      // Components can be extra fields, check if the field really exists.
      if (!$this->hasField($field_name)) {
        continue;
      }
      $field_definition = $this->getFieldDefinition($field_name);

      // Only take care for accessible string fields.
      if (!($field_definition instanceof FieldConfigInterface) || $field_definition->getType() !== 'string' || !$this->get($field_name)->access('view')) {
        continue;
      }

      if ($this->get($field_name)->isEmpty()) {
        continue;
      }

      foreach ($this->get($field_name) as $field_item) {
        $values[] = $field_item->value;
      }

      // Stop after two value items were received.
      if (count($values) > 2) {
        return implode(' ', array_slice($values, 0, 2)) . '...';
      }
    }

    return implode(' ', $values);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    // Add the published field.
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Authored by'))
      ->setDescription(t('The user ID of author of the Storage entity.'))
      ->setRevisionable(TRUE)
      ->setSetting('target_type', 'user')
      ->setSetting('handler', 'default')
      ->setTranslatable(TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'author',
        'region' => 'hidden',
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'region' => 'hidden',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => '60',
          'autocomplete_type' => 'tags',
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setTranslatable(TRUE)
      ->setRevisionable(TRUE)
      ->setSettings([
        'max_length' => 255,
        'text_processing' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setRequired(TRUE);

    $fields['status']
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'region' => 'hidden',
      ])
      ->setDisplayConfigurable('form', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entity was created.'));

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the entity was last edited.'));

    $fields['revision_translation_affected'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Revision translation affected'))
      ->setDescription(t('Indicates if the last edit of a translation belongs to current revision.'))
      ->setReadOnly(TRUE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE);

    return $fields;
  }

}
