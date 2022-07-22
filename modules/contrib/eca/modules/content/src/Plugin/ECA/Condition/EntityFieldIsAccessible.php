<?php

namespace Drupal\eca_content\Plugin\ECA\Condition;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\ECA\Condition\ConditionBase;

/**
 * Plugin implementation of the ECA condition for entity field is accessible.
 *
 * @EcaCondition(
 *   id = "eca_entity_field_is_accessible",
 *   label = @Translation("Entity: field is accessible"),
 *   description = @Translation("Checks whether the current user has operational access on an entity field."),
 *   context_definitions = {
 *     "entity" = @ContextDefinition("entity", label = @Translation("Entity"))
 *   }
 * )
 */
class EntityFieldIsAccessible extends ConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(): bool {
    $entity = $this->getValueFromContext('entity');
    if (!($entity instanceof FieldableEntityInterface)) {
      return FALSE;
    }
    $field_name = trim((string) $this->tokenServices->replaceClear($this->configuration['field_name'] ?? ''));
    if (($field_name === '') || !($entity->hasField($field_name))) {
      return FALSE;
    }
    $field_op = $this->configuration['operation'];
    $entity_op = $field_op === 'edit' ? 'update' : $field_op;
    return $this->negationCheck($entity->access($entity_op) && $entity->$field_name->access($field_op));
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'field_name' => '',
      'operation' => 'view',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field machine name'),
      '#default_value' => $this->configuration['field_name'] ?? '',
      '#required' => TRUE,
      '#weight' => -20,
    ];
    $form['operation'] = [
      '#title' => $this->t('Operation'),
      '#options' => [
        'view' => $this->t('View'),
        'edit' => $this->t('Edit'),
        'delete' => $this->t('Delete'),
      ],
      '#default_value' => $this->configuration['operation'] ?? 'view',
      '#required' => TRUE,
      '#weight' => -10,
    ];
    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    $this->configuration['operation'] = $form_state->getValue('operation');
    parent::submitConfigurationForm($form, $form_state);
  }

}
