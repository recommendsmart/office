<?php

namespace Drupal\widget_type\Element;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\FormElement;
use Drupal\Core\Render\Element\Radios;
use Drupal\Core\Site\Settings;
use Drupal\file\FileInterface;
use Drupal\widget_type\WidgetRegistrySourceInterface;
use Drupal\widget_type\WidgetTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a component selector element.
 *
 * @FormElement("widget_type_selector")
 */
class WidgetSelectorElement extends FormElement implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    $entity_type_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $entity_type_manager = $container->get('entity_type.manager');
    return new static($configuration, $plugin_id, $plugin_definition, $entity_type_manager);
  }

  /**
   * @inheritDoc
   */
  public function getInfo() {
    return [
      '#title' => $this->t('Widget Selector'),
      '#process' => [[$this, 'populateOptions']],
      '#element_validate' => [[$this, 'validateExistingWidgetType']],
      '#theme_wrappers' => ['widget_type_selector', 'fieldset'],
      '#submit' => [[$this, 'submitForm']],
      '#input' => TRUE,
    ];
  }

  /**
   * Form API process callback.
   */
  public function populateOptions(
    array &$element,
    FormStateInterface $form_state,
    array &$complete_form
  ): array {
    try {
      $storage = $this->entityTypeManager->getStorage('widget_type');
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      watchdog_exception('widget_type', $e);
      return $element;
    }
    $widget_types = isset($element['#options'])
      ? $storage->loadMultiple(array_keys($element['#options']))
      : $storage->loadMultiple();
    $options = array_map(
      static fn(WidgetTypeInterface $widget_type) => $widget_type->label(),
      $widget_types
    );
    ksort($options);

    $target_type = 'widget_type';
    $selection_handler = 'default:widget_type';
    $selection_settings = [
      'widget_type_fields' => [
        'name',
        'remote_widget_version',
        'widget_registry_source',
      ],
    ];
    $selection_settings_key = Crypt::hmacBase64(
      serialize($selection_settings) . $target_type . $selection_handler,
      Settings::getHashSalt()
    );
    \Drupal::keyValue('entity_autocomplete')->set($selection_settings_key, $selection_settings);

    $default_id = $element['#default_value']['target_id'] ?? NULL;
    $default_widget_type = ($default_id ? $widget_types[$default_id] : NULL) ?? NULL;
    $element += [
      '#attached' => ['library' => ['widget_type/selector']],
    ];

    // Widgets types can't be change once created, so hide search and deprecated searchbox
    if (!$default_widget_type) {
      $element['search'] = [
        '#title' => $this->t('Search'),
        '#title_display' => 'hidden',
        '#type' => 'search',
        '#default_value' => $default_widget_type instanceof WidgetTypeInterface ? $default_widget_type->getRemoteId(
        ) : NULL,
        '#placeholder' => $this->t('Search for a widget type'),
        '#size' => 50,
        '#description' => $this->t('Start typing to search for a widget type.'),
        '#input' => FALSE,
        '#attributes' => [
          'class' => [
            'search-box',
          ],
        ],
      ];
      $element['show_deprecated'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show deprecated widgets'),
        '#default_value' => FALSE,
        '#attributes' => [
          'class' => [
            'deprecation-checkbox',
          ],
        ],
      ];
    }

    $element['target_id'] = [
      '#type' => 'radios',
      '#options' => $options,
      '#title' => $this->t('Widgets'),
      '#title_display' => 'invisible',
      '#default_value' => $default_widget_type ? $default_widget_type->id() : NULL,
      '#process' => [
        [Radios::class, 'processRadios'],
        [$this, 'processRadios'],
      ],
      '#weight' => 1,
      '#attributes' => [
        'class' => ['widget-type-selector--radios'],
      ],
      '#ajax' => $element['#ajax'] ?? FALSE,
      '#input' => FALSE,
    ];
    $classes = $element['#attributes']['class'] ?? [];
    $classes[] = 'widget-type--selector';
    $element['#attributes']['class'] = $classes;
    unset($element['#default_value'], $element['#ajax'], $element['#options']);
    return $element;
  }

  /**
   * Process the radios.
   */
  public function processRadios(array $element, FormStateInterface $form_state): array {
    $keys = Element::children($element);
    try {
      $storage = $this->entityTypeManager->getStorage('widget_type');
    } catch (InvalidPluginDefinitionException|PluginNotFoundException  $e) {
      watchdog_exception('widget_type', $e);
      return $element;
    }
    $widget_type_ids = array_filter(
      array_map(
        static fn(array $item) => $item['#return_value'] ?? NULL,
        array_intersect_key($element, array_flip($keys))
      )
    );
    $widget_types = $storage->loadMultiple($widget_type_ids);
    foreach ($keys as $key) {
      $element[$key]['#theme_wrappers'] = [
        'form_element__radio__widget_type',
        'form_element__radio',
      ];
      $id = $element[$key]['#return_value'];
      $widget_type = $widget_types[$id];
      assert($widget_type instanceof WidgetTypeInterface);
      $source = $widget_type->get('widget_registry_source');
      $element[$key]['#title_display'] = 'hidden';
      $element[$key]['#entity'] = $widget_type;
      $element[$key]['#human_name'] = $widget_type->getName();
      $element[$key]['#machine_name'] = $widget_type->getRemoteId();
      $element[$key]['#remote_description'] = $widget_type->getDescription();
      $element[$key]['#remote_status'] = $widget_type->getRemoteStatus();
      $field_image = $widget_type->getPreviewImage();
      $thumbnail = ['#markup' => ''];
      $image = ['#markup' => ''];
      if ($field_image['file'] instanceof FileInterface) {
        $uri = $field_image['file']->getFileUri();
        $alt = $field_image['alt'] ?? $this->t('Thumbnail');
        $title = $field_image['title'] ?? $this->t('Add a thumbnail.png into your widget to make it show up here.');
        $thumbnail = [
          '#theme' => 'image_style',
          '#style_name' => 'widget_type_small_16_9',
          '#alt' => $alt,
          '#title' => $title,
          '#uri' => $uri,
          '#attributes' => ['loading' => 'lazy'],
        ];
        $image = [
          '#theme' => 'image',
          '#alt' => $alt,
          '#title' => $title,
          '#uri' => $uri,
          '#attributes' => [
            'loading' => 'lazy',
            'class' => ['radio-details--image'],
          ],
        ];
      }
      $element[$key]['#thumbnail'] = $thumbnail;
      $element[$key]['#image'] = $image;
      $source_entity = $source->entity instanceof WidgetRegistrySourceInterface ? $source->entity : NULL;
      $element[$key]['#preview_url'] = $widget_type->getPreviewLink();
      $element[$key]['#status'] = $widget_type->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
      $element[$key]['#remote_id'] = $widget_type->getRemoteId();
      $element[$key]['#version'] = $widget_type->getVersion();
      $element[$key]['#created_date'] = \Drupal::service('date.formatter')->format($widget_type->getCreatedTime());
      $element[$key]['#updated_date'] = \Drupal::service('date.formatter')->format($widget_type->getCreatedTime());
      $element[$key]['#source'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => '',
        '#attributes' => ['class' => 'radio-details--source'],
      ];
      if ($source_entity) {
        [$color, $complementary_color] = $source_entity->calculateColors();
        $element[$key]['#source']['#value'] = $source_entity->label();
        $element[$key]['#source']['#attributes'] = [
          'style' => 'color: #' . $color . '; background-color: #' . $complementary_color,
          'title' => $source_entity->label(),
          'class' => ['radio-details--source'],
        ];
      }
      $element[$key]['#remote_languages'] = [
        '#theme' => 'item_list',
        '#attributes' => ['class' => ['available-languages']],
        '#items' => array_map(
          static function (string $langcode) {
            return [
              '#type' => 'html_tag',
              '#tag' => 'code',
              '#value' => $langcode,
            ];
          },
          $widget_type->getRemoteLanguages()
        ),
      ];
    }
    return $element;
  }

  /**
   * Validator for form element.
   */
  public function validateExistingWidgetType(array $element, FormStateInterface $form_state): void {
    $parents = $element['#parents'] ?? [];
    $parents[] = 'target_id';
    $value = $form_state->getValue($parents);
    if (!is_scalar($value)) {
      $value = NULL;
      $form_state->setValue($parents, NULL);
    }
    if (!$value) {
      return;
    }
    try {
      $storage = $this->entityTypeManager->getStorage('widget_type');
      if ($storage->load($value)) {
        return;
      }
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
    }
    $form_state->setError(
      $element['target_id'],
      $this->t('Invalid component ID: @id', ['@id' => $value])
    );
  }

}
