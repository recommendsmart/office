<?php

namespace Drupal\base_field_override_ui\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Field\Entity\BaseFieldOverride;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\base_field_override_ui\BaseFieldOverrideUI;

/**
 *
 */
class BaseFieldOverrideForm extends EntityForm {

  /**
   * The entity being used by this form.
   *
   * @var \Drupal\field\FieldConfigInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#required' => TRUE,
      '#default_value' => $this->entity->getLabel(),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->entity->getDescription(),
    ];


    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save settings');

    if (!$this->entity->isNew()) {
      $url = BaseFieldOverrideUI::getDeleteRouteInfo($this->entity);

      if ($this->getRequest()->query->has('destination')) {
        $query = $url->getOption('query');
        $query['destination'] = $this->getRequest()->query->get('destination');
        $url->setOption('query', $query);
      }
      $actions['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#url' => $url,
        '#access' => $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
      ];
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $this->entity->save();

    $this->messenger()->addStatus($this->t('Saved %label configuration.', ['%label' => $this->entity->getLabel()]));

    $form_state->setRedirectUrl(BaseFieldOverrideUI::getOverviewRouteInfo($this->entity->getTargetEntityTypeId(), $this->entity->getTargetBundle()));
  }


  /**
   * The _title_callback for the field settings form.
   *
   * @param \Drupal\Core\Field\Entity\BaseFieldOverride $base_field_override
   *   The base field override.
   *
   * @return string
   *   The label of the field.
   */
  public function getTitle(BaseFieldOverride $base_field_override) {
    return $this->t('Edit @label base field', ['@label' => $base_field_override->label()]);
  }
}
