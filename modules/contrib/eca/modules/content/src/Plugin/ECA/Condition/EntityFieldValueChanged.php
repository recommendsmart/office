<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Plugin implementation of the ECA condition for changed entity field value.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_value_changed",
 *   label = @Translation("Entity: field value changed"),
 *   description = @Translation("Evaluates against the change of a value field of an entity."),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldValueChanged extends ConditionBase {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    $field_name = $this->tokenServices->replaceClear($this->configuration['field_name']);
    $options = ['access' => FALSE, 'auto_item' => FALSE];
    if (($entity instanceof EntityInterface) && isset($entity->original) && ($entity->original instanceof EntityInterface) && ($property = $this->getTypedProperty($entity->getTypedData(), $field_name, $options)) && ($original_property = $this->getTypedProperty($entity->original->getTypedData(), $field_name, $options))) {
      return $this->negationCheck($property->getValue() !== $original_property->getValue());
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The field name of the entity to check, if its value has changed.'),
      '#default_value' => $this->configuration['field_name'],
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
