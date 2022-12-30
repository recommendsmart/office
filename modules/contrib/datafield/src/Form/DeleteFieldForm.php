<?php

namespace Drupal\datafield\Form;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides delete form.
 *
 * @internal
 */
class DeleteFieldForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_data_field';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, $field_name = NULL, $delta = 0) {
    $form_state->set('entity', $entity);
    $form_state->set('field_name', $field_name);
    $form_state->set('delta', $delta);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#button_type' => 'danger',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    $field_name = $form_state->get('field_name');
    $delta = $form_state->get('delta');
    $entity->get($field_name)->removeItem($delta);
    $entity->save();
    $form_state->setRedirectUrl($entity->toUrl('canonical'));
  }

}
