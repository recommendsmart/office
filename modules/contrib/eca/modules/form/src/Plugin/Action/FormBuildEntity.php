<?php

namespace Drupal\eca_form\Plugin\Action;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\FormPluginTrait;

/**
 * Build an entity from submitted form input.
 *
 * @Action(
 *   id = "eca_form_build_entity",
 *   label = @Translation("Entity form: build entity"),
 *   description = @Translation("Build an entity from submitted form input and store the result as a token."),
 *   type = "form"
 * )
 */
class FormBuildEntity extends ConfigurableActionBase {

  use FormPluginTrait;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name of token'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('The built entity will be stored into this specified token. Please note: An entity can only be built when a form got submitted. Example events where it works: <em>Validate form</em>, <em>Submit form</em>.'),
      '#required' => TRUE,
      '#weight' => -45,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $form_state = $this->getCurrentFormState();
    $form_object = $form_state ? $form_state->getFormObject() : NULL;
    $result = $this->getCurrentForm() && ($form_object instanceof EntityFormInterface) && $form_state->isSubmitted() ? AccessResult::allowed() : AccessResult::forbidden();
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute(): void {
    $form_state = $this->getCurrentFormState();
    $form_object = $form_state ? $form_state->getFormObject() : NULL;
    $form = &$this->getCurrentForm();
    if (!$form || !($form_object instanceof EntityFormInterface) || !$form_state->isSubmitted()) {
      return;
    }

    // The form builder service usually clears the values before processing
    // a form. Since the entity should be built using submitted values though,
    // the values are being manually set. The same goes for ongoing validation.
    // @see \Drupal\Core\Form\FormBuilder::processForm()
    if ($form_state->isSubmitted() && (empty($form_state->getValues()) || !$form_state->isValidationComplete())) {
      // The form state is being cloned here to not interfere with the regular
      // form processing and to not leak raw or non-validated user input.
      $form_state = clone $form_state;
      // Set the values of the form state, taking existing processed values
      // with higher priority than unprocessed user input.
      $form_state->setValues(NestedArray::mergeDeep($form_state->getUserInput(), ($form_state->getValues() ?? [])));
    }

    $this->tokenServices->addTokenData($this->configuration['token_name'], $form_object->buildEntity($form, $form_state));
  }

}
