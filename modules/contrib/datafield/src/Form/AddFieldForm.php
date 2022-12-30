<?php

namespace Drupal\datafield\Form;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Add form.
 *
 * @internal
 */
class AddFieldForm extends FormBase {
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an \Drupal\views\Plugin\views\argument_validator\Entity object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'add_data_field';
  }

  /**
   * {@inheritdoc}
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The form add.
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, $field_name = NULL) {
    if (!$form_state->has('entity')) {
      $this->init($form_state, $entity, $field_name);
    }

    // Add the field form.
    $form_state->get('form_display')->buildForm($entity, $form, $form_state);

    // Add a dummy changed timestamp field to attach form errors to.
    if ($entity instanceof EntityChangedInterface) {
      $form['changed_field'] = [
        '#type' => 'hidden',
        '#value' => $entity->getChangedTime(),
      ];
    }

    // Add a submit button. Give it a class for easy JavaScript targeting.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => ['class' => ['quickedit-form-submit']],
    ];

    // Use the non-inline form error display for Quick Edit forms, because in
    // this case the errors are already near the form element.
    $form['#disable_inline_form_errors'] = TRUE;

    // Simplify it for optimal in-place use.
    $this->simplify($form, $form_state);

    return $form;
  }

  /**
   * Initialize the form state and the entity before the first form build.
   */
  protected function init(FormStateInterface $form_state, EntityInterface $entity, $field_name) {
    // @todo Rather than special-casing $node->revision, invoke prepareEdit()
    //   once https://www.drupal.org/node/1863258 lands.
    if ($entity->getEntityTypeId() == 'node') {
      $node_type = $this->entityTypeManager->getStorage('node_type')
        ->load($entity->bundle());
      $entity->setNewRevision($node_type->shouldCreateNewRevision());
      $entity->revision_log = NULL;
    }

    $form_state->set('entity', $entity);
    $form_state->set('field_name', $field_name);

    // Fetch the display used by the form. It is the display for the 'default'
    // form mode, with only the current field visible.
    $display = EntityFormDisplay::collectRenderDisplay($entity, 'default');
    foreach ($display->getComponents() as $name => $options) {
      if ($name != $field_name) {
        $display->removeComponent($name);
      }
    }
    $form_state->set('form_display', $display);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->buildEntity($form, $form_state);
    $form_state->get('form_display')
      ->validateFormValues($entity, $form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * Saves the entity with updated values for the edited field.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->buildEntity($form, $form_state);
    $form_state->set('entity', $entity);
    $entity->save();
    $form_state->setRedirectUrl($entity->toUrl('canonical'));
  }

  /**
   * Returns a cloned entity containing updated field values.
   *
   * Calling code may then validate the returned entity, and if valid, transfer
   * it back to the form state and save it.
   */
  protected function buildEntity(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\EntityInterface $entity */
    $entity = clone $form_state->get('entity');
    $field_name = $form_state->get('field_name');

    $form_state->get('form_display')
      ->extractFormValues($entity, $form, $form_state);

    // @todo Refine automated log messages and abstract them to all entity
    //   types: https://www.drupal.org/node/1678002.
    if ($entity->getEntityTypeId() == 'node' && $entity->isNewRevision() && $entity->revision_log->isEmpty()) {
      $entity->revision_log = $this->t('Updated the %field-name field through in-place editing.', [
        '%field-name' => $entity->get($field_name)
          ->getFieldDefinition()
          ->getLabel(),
      ]);
    }

    return $entity;
  }

  /**
   * Simplifies the field edit form for in-place editing.
   *
   * This function:
   * - Hides the field label inside the form, because JavaScript displays it
   *   outside the form.
   * - Adjusts textarea elements to fit their content.
   *
   * @param array &$form
   *   A reference to an associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  protected function simplify(array &$form, FormStateInterface $form_state) {
    $field_name = $form_state->get('field_name');
    $widget_element =& $form[$field_name]['widget'];

    // Hide the field label from displaying within the form, because JavaScript
    // displays the equivalent label that was provided within an HTML data
    // attribute of the field's display element outside of the form. Do this for
    // widgets without child elements (like Option widgets) as well as for ones
    // with per-delta elements. Skip single checkboxes, because their title is
    // key to their UI. Also skip widgets with multiple subelements, because in
    // that case, per-element labeling is informative.
    $num_children = count(Element::children($widget_element));
    if ($num_children == 0 && $widget_element['#type'] != 'checkbox') {
      $widget_element['#title_display'] = 'invisible';
    }
    if ($num_children == 1 && isset($widget_element[0]['value'])) {
      // @todo While most widgets name their primary element 'value', not all
      //   do, so generalize this.
      $widget_element[0]['value']['#title_display'] = 'invisible';
    }

    // Adjust textarea elements to fit their content.
    if (isset($widget_element[0]['value']['#type']) && $widget_element[0]['value']['#type'] == 'textarea') {
      $lines = count(explode("\n", $widget_element[0]['value']['#default_value']));
      $widget_element[0]['value']['#rows'] = $lines + 1;
    }
  }

}
