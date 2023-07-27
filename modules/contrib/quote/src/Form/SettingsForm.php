<?php

namespace Drupal\quote\Form;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Settings Form.
 *
 * @package Drupal\quote\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * A cache backend interface instance.
   */
  protected CacheBackendInterface $cacheRender;

  /**
   * Module handler.
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * SettingsForm constructor.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheRender
   *   CacheBackendInterface.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   ModuleHandlerInterface.
   */
  public function __construct(CacheBackendInterface $cacheRender, ModuleHandlerInterface $moduleHandler) {
    $this->cacheRender = $cacheRender;
    $this->moduleHandler = $moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.render'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'quote.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'quote_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('quote.settings');

    $form['modes'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Select quote modes'),
    ];
    $form['modes']['quote_modes_quote_sel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Quote selected'),
      '#default_value' => $config->get('quote_modes_quote_sel'),
    ];
    $form['modes']['quote_modes_quote_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Quote all'),
      '#default_value' => $config->get('quote_modes_quote_all'),
    ];
    $form['modes']['quote_modes_quote_reply_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Quote and reply all'),
      '#default_value' => $config->get('quote_modes_quote_reply_all'),
    ];
    $form['modes']['quote_modes_quote_reply_sel'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Quote and reply selected'),
      '#default_value' => $config->get('quote_modes_quote_reply_sel'),
      '#disabled' => TRUE,
    ];

    $form['where'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Allow quotes in the:'),
    ];
    $form['where']['quote_allow_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Content types'),
      '#options' => \node_type_get_names(),
      '#default_value' => $config->get('quote_allow_types'),
    ];
    $form['where']['quote_allow_comments'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Comments'),
      '#description' => $this->t('Checkbox works if node type allow quoting'),
      '#default_value' => $config->get('quote_allow_comments'),
    ];

    $form['ckeditor_support_set'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('CKEditor support'),
    ];
    $form['ckeditor_support_set']['quote_ckeditor_support'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('CKEditor support'),
      '#description' => $this->t('If checkbox checked and CKEditor found on the page, CKEditor will have a priority'),
      '#default_value' => $config->get('quote_ckeditor_support'),
      '#disabled' => !$this->moduleHandler->moduleExists('ckeditor'),
    ];

    $form['other'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other settings'),
    ];
    $form['other']['quote_selector'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS selector of your comment form textarea (where you write new comments)'),
      '#description' => $this->t('By default it is: #comment-form textarea'),
      '#default_value' => $config->get('quote_selector'),
    ];
    $form['other']['quote_selector_comment_quote_all'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS selector of your comment body class (where you quote all)'),
      '#description' => $this->t('By default it is: .field--name-comment-body'),
      '#default_value' => $config->get('quote_selector_comment_quote_all'),
    ];
    $form['other']['quote_selector_node_quote_all'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS selector of your node body class (where you quote all)'),
      '#description' => $this->t('By default it is: .field--name-body'),
      '#default_value' => $config->get('quote_selector_node_quote_all'),
    ];
    $form['other']['quote_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Quote limit'),
      '#default_value' => $config->get('quote_limit'),
      '#max' => 9999,
      '#min' => 1,
    ];
    $form['other']['quote_html_tags_support'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Allow HTML tags in quotes'),
      '#default_value' => $config->get('quote_html_tags_support'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $allow_types = \array_filter($form_state->getValue('quote_allow_types', []));
    $this->config('quote.settings')
      ->set('quote_modes_quote_sel', $form_state->getValue('quote_modes_quote_sel'))
      ->set('quote_modes_quote_all', $form_state->getValue('quote_modes_quote_all'))
      ->set('quote_modes_quote_reply_all', $form_state->getValue('quote_modes_quote_reply_all'))
      ->set('quote_modes_quote_reply_sel', $form_state->getValue('quote_modes_quote_reply_sel'))
      ->set('quote_allow_comments', $form_state->getValue('quote_allow_comments'))
      ->set('quote_allow_types', $allow_types)
      ->set('quote_selector', $form_state->getValue('quote_selector'))
      ->set('quote_limit', $form_state->getValue('quote_limit'))
      ->set('quote_selector_comment_quote_all', $form_state->getValue('quote_selector_comment_quote_all'))
      ->set('quote_selector_node_quote_all', $form_state->getValue('quote_selector_node_quote_all'))
      ->set('quote_ckeditor_support', $form_state->getValue('quote_ckeditor_support'))
      ->set('quote_html_tags_support', $form_state->getValue('quote_html_tags_support'))
      ->save();

    $this->cacheRender->invalidateAll();
  }

}
