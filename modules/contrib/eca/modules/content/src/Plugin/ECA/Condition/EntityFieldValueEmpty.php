<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;
use Drupal\eca\TypedData\PropertyPathTrait;

/**
 * Plugin implementation of the ECA condition for empty entity field value.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_value_empty",
 *   label = @Translation("Entity: field value is empty"),
 *   description = @Translation("Evaluates if a value field of an entity is empty."),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldValueEmpty extends ConditionBase {

  use PropertyPathTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    $field_name = $this->tokenServices->replaceClear($this->configuration['field_name']);
    $options = ['access' => FALSE, 'auto_item' => FALSE];
    if (($entity instanceof EntityInterface) && ($property = $this->getTypedProperty($entity->getTypedData(), $field_name, $options))) {
      return $this->negationCheck(method_exists($property, 'isEmpty') ? $property->isEmpty() : empty($property->getValue()));
    }
    // Stop execution chain when field or property does not exist.
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
      '#description' => $this->t('The field name of the entity to check, if its value is empty.'),
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
