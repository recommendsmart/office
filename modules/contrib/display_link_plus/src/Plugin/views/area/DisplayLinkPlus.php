<?php

namespace Drupal\display_link_plus\Plugin\views\area;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\views\Plugin\views\area\AreaPluginBase;
use Drupal\views\Plugin\views\display\PathPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Defines an area plugin to display a bundle-specific node/add link.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("display_link_plus")
 */
class DisplayLinkPlus extends AreaPluginBase {
  use RedirectDestinationTrait;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * The current user.
   *
   * We'll need this service in order to check view access.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   The access manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccessManagerInterface $access_manager, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->accessManager = $access_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('access_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['display_id'] = ['default' => NULL];
    $options['label'] = ['default' => NULL];
    $options['class'] = ['default' => NULL];
    $options['target'] = ['default' => ''];
    $options['width'] = ['default' => '600'];
    $options['append_destination'] = ['default' => FALSE];
    $options['arguments_mapping'] = ['default' => []];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // All displays.
    // $displays = $this->view->storage->get('display');
    $display_objects = $this->view->displayHandlers;
    $displays = [];

    foreach($display_objects as $display_object) {
      if ($this->isPathBasedDisplay($display_object->display['id'])) {
        $displays[$display_object->display['id']] = $display_object->display['display_title'];
      }
    }
    $form['display_id'] = [
      '#title' => $this->t('Display'),
      '#description' => $this->t('The display to which we should link.'),
      '#type' => 'select',
      '#options' => $displays,
      '#default_value' => (!empty($this->options['display_id'])) ? $this->options['display_id'] : '',
      '#required' => TRUE,
    ];
    $form['label'] = [
      '#title' => $this->t('Label'),
      '#description' => $this->t('The text of the link. Leave blank to use the display\'s title'),
      '#type' => 'textfield',
      '#default_value' => $this->options['label'],
    ];
    // TODO: allow for multiple classes.
    $form['class'] = [
      '#title' => $this->t('Class'),
      '#description' => $this->t('A CSS class to apply to the link. If using multiple classes, separate them by spaces.'),
      '#type' => 'textfield',
      '#default_value' => $this->options['class'],
    ];
    $form['target'] = [
      '#title' => $this->t('Target'),
      '#description' => $this->t('Optionally have the form open on-page in a modal or off-canvas dialog.'),
      '#type' => 'select',
      '#default_value' => $this->options['target'],
      '#options' => [
        '' => $this->t('Default'),
        'tray' => $this->t('Off-Screen Tray'),
        'modal' => $this->t('Modal Dialog'),
      ],
    ];
    $form['width'] = [
      '#title' => $this->t('Dialog Width'),
      '#description' => $this->t('How wide the dialog should appear.'),
      '#type' => 'number',
      '#min' => '100',
      '#default_value' => $this->options['width'],
      '#states' => [
        // Show this number field only if a dialog is chosen above.
        'invisible' => [
          ':input[name="options[target]"]' => ['value' => ''],
        ],
      ],
    ];
    $form['append_destination'] = [
      '#title' => $this->t('Append destination parameter'),
      '#description' => $this->t('If a destination query parameter should be added to the link.'),
      '#type' => 'checkbox',
      '#default_value' => $this->options['append_destination'],
    ];

    $display_arguments = $display_objects->get($this->view->current_display)->getHandlers('argument');
    if (!empty($display_arguments)) {
      $form['arguments_mapping'] = [
        '#type' => 'details',
        '#title' => $this->t('Arguments mapping'),
        '#tree' => TRUE,
      ];

      foreach ($display_arguments as $argument_machine_name => $argument) {
        $form['arguments_mapping'][$argument_machine_name] = [
          '#type' => 'details',
          '#title' => $this->t('Argument mapping for %argument_title', [
            '%argument_title' => $argument->adminLabel(),
          ]),
          '#open' => TRUE,
        ];
        $form['arguments_mapping'][$argument_machine_name]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable mapping'),
          '#default_value' => $this->options['arguments_mapping'][$argument_machine_name]['enabled'] ?? FALSE,
        ];
        $form['arguments_mapping'][$argument_machine_name]['query_string'] = [
          '#type' => 'textfield',
          '#title' => $this->t('The query string to use for this argument'),
          '#default_value' => $this->options['arguments_mapping'][$argument_machine_name]['query_string'] ?? '',
          '#states' => [
            'invisible' => [
              ':input[name="options[arguments_mapping][' . $argument_machine_name . '][enabled]"]' => ['checked' => FALSE],
            ],
          ],
        ];
        $form['arguments_mapping'][$argument_machine_name]['is_multiple'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Multiple'),
          '#description' => $this->t('If multiple values should be handled. If left unchecked and the contextual filter allows multiple values, the first one will be used.'),
          '#default_value' => $this->options['arguments_mapping'][$argument_machine_name]['is_multiple'] ?? FALSE,
          '#states' => [
            'invisible' => [
              ':input[name="options[arguments_mapping][' . $argument_machine_name . '][enabled]"]' => ['checked' => FALSE],
            ],
          ],
        ];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    if ($empty && empty($this->options['display_id'])) {
      return [];
    }

    if (!$this->isPathBasedDisplay($this->options['display_id'])) {
      return [];
    }

    $account = $this->currentUser;
    $access = $this->view->access($this->options['display_id'], $account);
    $url = $this->view->getUrl(NULL, $this->options['display_id']);
    $url->setOption('query', $this->getQueryParameter());

    if (empty($this->options['label'])) {
      $display_objects = $this->view->displayHandlers;

      foreach($display_objects as $display_object) {
        if ($display_object->display['id'] == $this->options['display_id']) {
          $this->options['label'] = $display_object->display['display_title'];
          break;
        }
      }
    }

    // Parse and sanitize provided classes.
    if ($this->options['class']) {
      $classes = explode(' ', $this->options['class']);
      foreach ($classes as $index => $class) {
        $classes[$index] = Html::getClass($class);
      }
    }
    else {
      $classes = [];
    }
    // Assembled elements into a link render array.
    $element = [
      '#type' => 'link',
      '#title' => $this->options['label'],
      '#url' => $url,
      '#options' => [
        'attributes' => ['class' => $classes],
      ],
      '#access' => $access,
    ];
    // Apply the selected dialog options.
    if ($this->options['target']) {
      $element['#options']['attributes']['class'][] = 'use-ajax';
      $width = $this->options['width'] ?: 600;
      $element['#options']['attributes']['data-dialog-options'] = Json::encode(['width' => $width]);
      switch ($this->options['target']) {
        case 'tray':
          $element['#options']['attributes']['data-dialog-renderer'] = 'off_canvas';
          $element['#options']['attributes']['data-dialog-type'] = 'dialog';
          break;

        case 'modal':
          $element['#options']['attributes']['data-dialog-type'] = 'modal';
          break;
      }
    }
    return $element;
  }

  /**
   * Prepare the link query parameter.
   *
   * @return array
   */
  protected function getQueryParameter() : array {
    $query = $this->view->getExposedInput();
    if ($current_page = $this->view->getCurrentPage()) {
      $query['page'] = $current_page;
    }

    // @todo Remove this parsing once these are removed from the request in
    //   https://www.drupal.org/node/2504709.
    foreach ([
      'view_name',
      'view_display_id',
      'view_args',
      'view_path',
      'view_dom_id',
      'pager_element',
      'view_base_path',
      AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER,
      FormBuilderInterface::AJAX_FORM_REQUEST,
      MainContentViewSubscriber::WRAPPER_FORMAT,
    ] as $key) {
      unset($query[$key]);
    }

    foreach ($this->options['arguments_mapping'] as $argument_machine_name => $argument_mapping) {
      if (!$argument_mapping['enabled'] || empty($argument_mapping['query_string'])) {
        continue;
      }

      if (!isset($this->view->argument[$argument_machine_name])) {
        continue;
      }
      if (empty($this->view->argument[$argument_machine_name]->value)) {
        continue;
      }

      $argument_values = $this->view->argument[$argument_machine_name]->value;

      if ($argument_mapping['is_multiple']) {
        $query[$argument_mapping['query_string']] = $argument_values;
      }
      else {
        $query[$argument_mapping['query_string']] = array_shift($argument_values);
      }
    }

    if ($this->options['append_destination']) {
      $query = array_merge($query, $this->getDestinationArray());
    }

    return $query;
  }

  /**
   * Check if a views display is a path-based display.
   *
   * @param string $display_id
   *   The display ID to check.
   *
   * @return bool
   *   Whether the display ID is an allowed display or not.
   */
  protected function isPathBasedDisplay($display_id) {
    $loaded_display = $this->view->displayHandlers->get($display_id);
    return $loaded_display instanceof PathPluginBase;
  }

}
