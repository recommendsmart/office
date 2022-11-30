<?php

namespace Drupal\eca_base\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_base\Plugin\ListCountTrait;

/**
 * Action to count items in a list and store resulting number as token.
 *
 * @Action(
 *   id = "eca_count",
 *   label = @Translation("List: count items"),
 *   description = @Translation("Counts the items in a list based on several properties.")
 * )
 */
class ListCount extends ConfigurableActionBase {

  use ListCountTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $result = $this->countValue($this->configuration['list_token']);
    $this->tokenServices->addTokenData($this->configuration['token_name'], $result);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'list_token' => '',
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['list_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token containing the list'),
      '#description' => $this->t('Provide the name of the token that contains a list from which the number of items should be counted.'),
      '#default_value' => $this->configuration['list_token'],
      '#weight' => -20,
    ];
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#description' => $this->t('Provide the name of a new token where the result should be stored.'),
      '#default_value' => $this->configuration['token_name'],
      '#weight' => -10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['list_token'] = $form_state->getValue('list_token');
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

}
