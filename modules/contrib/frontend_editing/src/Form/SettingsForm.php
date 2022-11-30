<?php

namespace Drupal\frontend_editing\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure frontend_editing settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a SettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'frontend_editing_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['frontend_editing.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('frontend_editing.settings');

    $entity_types = $this->entityTypeManager->getDefinitions();
    $labels = [];
    foreach ($entity_types as $entity_type_id => $entity_type) {
      if (!$entity_type instanceof ContentEntityTypeInterface) {
        continue;
      }
      $labels[$entity_type_id] = $entity_type->getLabel() ?: $entity_type_id;
    }
    asort($labels);

    $form['entity_types'] = [
      '#title' => $this->t('Entity types'),
      '#type' => 'checkboxes',
      '#options' => $labels,
      '#default_value' => $config->get('entity_types'),
    ];

    $form['width_settings'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Width settings'),
    ];

    $form['width_settings']['sidebar_width'] = [
      '#title' => $this->t('Sidebar width'),
      '#type' => 'number',
      '#default_value' => $config->get('sidebar_width') ?? 30,
      '#description' => $this->t('Set the width of the editing sidebar when it opens. Minimum width is 30%.'),
      '#min' => 30,
      '#max' => 40,
      '#required' => TRUE,
    ];

    $form['width_settings']['full_width'] = [
      '#title' => $this->t('Full width'),
      '#type' => 'number',
      '#default_value' => $config->get('full_width') ?? 70,
      '#description' => $this->t('Set the width of the editing sidebar when it is expanded. Minimum width is 50%.'),
      '#min' => 50,
      '#max' => 95,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('frontend_editing.settings')
      ->set('entity_types', $form_state->getValue('entity_types'))
      ->set('sidebar_width', $form_state->getValue('sidebar_width'))
      ->set('full_width', $form_state->getValue('full_width'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
