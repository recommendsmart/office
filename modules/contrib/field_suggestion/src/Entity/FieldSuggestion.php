<?php

namespace Drupal\field_suggestion\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityPublishedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\field_suggestion\FieldSuggestionInterface;

/**
 * Defines the field suggestion entity class.
 *
 * @ContentEntityType(
 *   id = "field_suggestion",
 *   label = @Translation("Field suggestion"),
 *   label_collection = @Translation("Field suggestion"),
 *   label_singular = @Translation("Field suggestion"),
 *   label_plural = @Translation("Field suggestions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count field suggestion",
 *     plural = "@count field suggestions"
 *   ),
 *   bundle_label = @Translation("Field suggestion type"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\field_suggestion\Form\FieldSuggestionForm",
 *       "edit" = "Drupal\field_suggestion\Form\FieldSuggestionForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\field_suggestion\Entity\FieldSuggestionRouteProvider",
 *     },
 *     "list_builder" = "Drupal\field_suggestion\FieldSuggestionListBuilder",
 *   },
 *   base_table = "field_suggestion",
 *   data_table = "field_suggestion_field_data",
 *   admin_permission = "administer field suggestion",
 *   entity_keys = {
 *     "id" = "fsid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *     "status" = "once",
 *     "published" = "once",
 *   },
 *   bundle_entity_type = "field_suggestion_type",
 *   field_ui_base_route = "entity.field_suggestion_type.edit_form",
 *   common_reference_target = TRUE,
 *   permission_granularity = "bundle",
 *   links = {
 *     "collection" = "/admin/content/field-suggestion",
 *     "edit-form" = "/admin/content/field-suggestion/{field_suggestion}",
 *     "delete-form" = "/admin/content/field-suggestion/{field_suggestion}/delete",
 *   }
 * )
 */
class FieldSuggestion extends ContentEntityBase implements FieldSuggestionInterface {

  use EntityPublishedTrait {
    isPublished as isOnce;
    setPublished as setOnce;
  }

  /**
   * {@inheritdoc}
   */
  public function type() {
    return $this->get('entity_type')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function field() {
    return $this->get('field_name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function isIgnored() {
    return (bool) $this->get('ignore')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setIgnored(bool $ignored) {
    $this->set('ignore', $ignored);

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasExcluded() {
    return !$this->get('exclude')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function getExcluded() {
    return $this->get('exclude')->getValue();
  }

  /**
   * {@inheritdoc}
   */
  public function countExcluded() {
    return $this->get('exclude')->count();
  }

  /**
   * {@inheritdoc}
   */
  public function label($original = FALSE) {
    /** @var \Drupal\field_suggestion\Service\FieldSuggestionHelperInterface $helper */
    $helper = \Drupal::service('field_suggestion.helper');

    $field = $this->get($helper->field($this->bundle()));

    $item = $field->first()->getValue();
    $value = reset($item);

    if (!$original && $field instanceof EntityReferenceFieldItemListInterface) {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $manager */
      $manager = \Drupal::service('entity_field.manager');

      $type = $manager->getBaseFieldDefinitions($this->type())[$this->field()]
        ->getItemDefinition()
        ->getSetting('target_type');

      $entity = \Drupal::entityTypeManager()->getStorage($type)->load($value);

      if ($entity !== NULL) {
        $value = $entity->label();
      }
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::publishedBaseFieldDefinitions($entity_type);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setRequired(TRUE);

    $fields['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field name'))
      ->setRequired(TRUE);

    $fields['ignore'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Ignore'))
      ->setReadOnly(TRUE);

    $fields['once']
      ->setLabel(t('Allowed number of usages'))
      ->setDescription(t('How many times this suggestion can be re-used.'))
      ->setRequired(TRUE)
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setDefaultValue(FALSE)
      ->setInitialValue(FALSE)
      ->setSettings([
        'on_label' => 'Once',
        'off_label' => 'Unlimited',
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 1,
      ]);

    return $fields;
  }

}
