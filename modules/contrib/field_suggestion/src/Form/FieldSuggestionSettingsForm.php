<?php

namespace Drupal\field_suggestion\Form;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Displays the Field Suggestion settings form.
 */
class FieldSuggestionSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The helper.
   *
   * @var \Drupal\field_suggestion\Service\FieldSuggestionHelperInterface
   */
  protected $helper;

  /**
   * The field names duplication resolver.
   *
   * Shows which entity type fields have an extra character in the name to not
   * duplicate the name of other fields.
   *
   * @var bool[]
   */
  protected $hasSuffix = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container)
      ->setStringTranslation($container->get('string_translation'));

    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->entityFieldManager = $container->get('entity_field.manager');
    $instance->helper = $container->get('field_suggestion.helper');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['field_suggestion.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'field_suggestion_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config($this->getEditableConfigNames()[0]);

    $form['collapsible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Collapsible'),
      '#description' => $this->t('Display suggestions list in drop-down view.'),
      '#default_value' => $config->get('collapsible'),
    ];

    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Limit'),
      '#description' => $this->t('The number of displayed values in a list of suggestions.'),
      '#default_value' => $config->get('limit'),
      '#min' => 1,
    ];

    $form['own'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Filter by owner'),
      '#description' => $this->t('Looking for suggestions only in entities of the current user.'),
      '#default_value' => $config->get('own'),
    ];

    $form['entity_types'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Entity types'),
      '#attached' => [
        'library' => ['field_suggestion/base'],
      ],
    ];

    $selected_fields = (array) $config->get('fields');

    if (!empty($selected_fields)) {
      $form['entity_types']['#default_tab'] = 'edit-' . str_replace('_', '-', array_keys($selected_fields)[0]);
    }

    $storage = $this->entityTypeManager->getStorage('field_suggestion_type');
    $field_types = $storage->getQuery()->execute();
    $definitions = $this->entityTypeManager->getDefinitions();

    unset($definitions['field_suggestion']);

    foreach ($definitions as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }

      $allowed_fields = $allowed_types = [];

      foreach ($this->entityFieldManager->getBaseFieldDefinitions($entity_type_id) as $field_name => $field) {
        if (
          !$field->isReadOnly() &&
          $field->getDisplayOptions('form') !== NULL
        ) {
          $key = $field_name . '|' . ($field_type = $field->getType());
          $allowed_fields[$key] = $field->getLabel();

          if (
            isset($selected_fields[$entity_type_id]) &&
            in_array($field_name, $selected_fields[$entity_type_id]) &&
            in_array($field_type, $field_types)
          ) {
            $allowed_types[] = $key;
          }
        }
      }

      if (empty($allowed_fields)) {
        continue;
      }

      $this->hasSuffix[$entity_type_id] = isset($form[$entity_type_id]);

      $form[$entity_type_id . ($this->hasSuffix[$entity_type_id] ? '_' : '')] = [
        '#type' => 'details',
        '#title' => $entity_type->getLabel(),
        '#group' => 'entity_types',
        '#tree' => TRUE,
        'fields' => [
          '#type' => 'checkboxes',
          '#title' => $this->t('Fields'),
          '#options' => $allowed_fields,
          '#default_value' => $allowed_types,
        ],
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $all_fields = [];

    $storage = $this->entityTypeManager->getStorage('field_suggestion_type');
    $field_types = $storage->getQuery()->execute();

    foreach ($this->hasSuffix as $entity_type => $has_suffix) {
      $fields = array_filter(array_values($form_state->getValue([
        $entity_type . ($has_suffix ? '_' : ''),
        'fields',
      ])));

      foreach ($fields as $field) {
        [$field_name, $field_type] = explode('|', $field);

        $all_fields[$entity_type][] = $field_name;

        if (!in_array($field_type, $field_types)) {
          $this->helper->bundle($field_type);
          $field_types[] = $field_type;
        }
      }
    }

    $config = $this->config($this->getEditableConfigNames()[0])
      ->set('fields', $all_fields);

    foreach (['collapsible', 'limit', 'own'] as $field) {
      $config->set($field, $form_state->getValue($field));
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
