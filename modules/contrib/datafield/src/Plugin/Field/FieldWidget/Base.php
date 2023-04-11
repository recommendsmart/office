<?php

namespace Drupal\datafield\Plugin\Field\FieldWidget;

use Drupal\Component\Plugin\PluginManagerBase;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\datafield\Plugin\Field\FieldType\DataField as FieldItem;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for data field formatters.
 */
abstract class Base extends WidgetBase {

  /**
   * The help topic plugin manager.
   *
   * @var \Drupal\help_topics\HelpTopicPluginManagerInterface
   */
  protected $pluginManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs a WidgetBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Component\Plugin\PluginManagerBase $plugin_manager
   *   The widget plugin manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, PluginManagerBase $plugin_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->pluginManager = $plugin_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($plugin_id, $plugin_definition,
      $configuration['field_definition'], $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('plugin.manager.field.widget'),
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $settings = [
      'inline' => FALSE,
      'widget_settings' => [],
    ];

    return $settings + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function widgetDefault() {
    return [
      'type' => NULL,
      'label_display' => 'block',
      'field_display' => TRUE,
      'size' => 30,
      'placeholder' => '',
      'label' => t('Ok'),
      'cols' => 10,
      'rows' => 5,
      'plugin' => '',
      'file_extensions' => 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp docx xlsx pptx',
      'preview_image_style' => 'thumbnail',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $widget_settings_default = self::widgetDefault();
    $field_settings = $this->getFieldSetting('field_settings');
    $settings = $this->getSettings();
    $widget_settings = $settings['widget_settings'];
    $subfields = array_keys($columns = $this->getFieldSetting("columns"));
    if (!empty($setting = $settings['widget_settings'])) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = array_keys($setting);
    }
    $types = FieldItem::subfieldTypes();

    $field_name = $this->fieldDefinition->getName();

    $element['inline'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display as inline element'),
      '#default_value' => $settings['inline'] ?? '',
    ];

    foreach ($subfields as $subfield) {
      if (empty($columns[$subfield])) {
        continue;
      }
      $item = $columns[$subfield];
      $type = $item['type'];
      $element['widget_settings'][$subfield] = [
        '#type' => 'details',
        '#title' => ($field_settings[$subfield]['label'] ?? $subfield) . ' - ' . $types[$type],
        '#open' => FALSE,
      ];

      $element['widget_settings'][$subfield]['type'] = [
        '#type' => 'select',
        '#title' => $this->t('Widget'),
        '#default_value' => $widget_settings[$subfield]["type"] ?? $widget_settings_default['type'],
        '#required' => TRUE,
        '#empty_option' => $this->t('- Select -'),
        '#options' => $this->getSubwidgets($type, $field_settings[$subfield]['list'] ?? FALSE, $item['datetime_type'] ?? FALSE),
      ];

      $options = [
        'block' => $this->t('Above'),
        'hidden' => $this->t('Hidden'),
      ];
      if ($settings['widget_settings'][$subfield]['type'] != 'datetime') {
        $options['inline'] = $this->t('Inline');
        $options['invisible'] = $this->t('Invisible');
      }
      $element['widget_settings'][$subfield]['label_display'] = [
        '#type' => 'select',
        '#title' => $this->t('Label'),
        '#default_value' => $widget_settings[$subfield]['label_display'] ?? $widget_settings_default['label_display'],
        '#options' => $options,
        '#empty_option' => $this->t('- Select -'),
        '#access' => static::isLabelSupported($item['type']),
      ];
      $element['widget_settings'][$subfield]['field_display'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show field'),
        '#default_value' => $widget_settings[$subfield]['field_display'] ?? $widget_settings_default['field_display'],
      ];
      $type_selector = "select[name='fields[$field_name][settings_edit_form][settings][widget_settings][$subfield][type]'";
      $element['widget_settings'][$subfield]['size'] = [
        '#type' => 'number',
        '#title' => $this->t('Size'),
        '#default_value' => $widget_settings[$subfield]['size'] ?? $widget_settings_default['size'],
        '#min' => 1,
        '#states' => [
          'visible' => [
            [$type_selector => ['value' => 'textfield']],
            [$type_selector => ['value' => 'email']],
            [$type_selector => ['value' => 'tel']],
            [$type_selector => ['value' => 'url']],
          ],
        ],
      ];
      if (!in_array($item["type"], [
        'datetime_iso8601',
        'date',
        'boolean',
      ])) {
        $element['widget_settings'][$subfield]['placeholder'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Placeholder'),
          '#default_value' => $widget_settings[$subfield]['placeholder'] ?? $widget_settings_default['placeholder'],
          '#states' => [
            'visible' => [
              [$type_selector => ['value' => 'textfield']],
              [$type_selector => ['value' => 'textarea']],
              [$type_selector => ['value' => 'email']],
              [$type_selector => ['value' => 'tel']],
              [$type_selector => ['value' => 'url']],
              [$type_selector => ['value' => 'number']],
              [$type_selector => ['value' => 'password']],
              [$type_selector => ['value' => 'text_format']],
              [$type_selector => ['value' => 'date']],
              [$type_selector => ['value' => 'json']],
            ],
          ],
        ];
      }

      $element['widget_settings'][$subfield]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $widget_settings[$subfield]['label'] ?? $widget_settings_default['label'],
        '#states' => [
          'visible' => [$type_selector => ['value' => 'checkbox']],
        ],
      ];

      if (in_array($item["type"], ['text', 'json', 'blob'])) {
        $element['widget_settings'][$subfield]['cols'] = [
          '#type' => 'number',
          '#title' => $this->t('Columns'),
          '#default_value' => $widget_settings[$subfield]['cols'] ?? $widget_settings_default['cols'],
          '#min' => 1,
          '#description' => $this->t('How many columns wide the textarea should be'),
          '#states' => [
            'visible' => [
              $type_selector => [
                ['value' => 'textarea'],
                ['value' => 'json'],
              ],
            ],
          ],
        ];

        $element['widget_settings'][$subfield]['rows'] = [
          '#type' => 'number',
          '#title' => $this->t('Rows'),
          '#default_value' => $widget_settings[$subfield]['rows'] ?? $widget_settings_default['rows'],
          '#min' => 1,
          '#description' => $this->t('How many rows high the textarea should be.'),
          '#states' => [
            'visible' => [
              $type_selector => [
                ['value' => 'textarea'],
                ['value' => 'json'],
              ],
            ],
          ],
        ];
      }

      if (in_array($item["type"], ['file'])) {
        $element['widget_settings'][$subfield]['preview_image_style'] = [
          '#title' => $this->t('Preview image style'),
          '#type' => 'select',
          '#options' => image_style_options(FALSE),
          '#empty_option' => '<' . $this->t('no preview') . '>',
          '#default_value' => $widget_settings[$subfield]['preview_image_style'] ?? $widget_settings_default['preview_image_style'],
          '#description' => $this->t('The preview image will be shown while editing the content.'),
          '#states' => [
            'visible' => [
              $type_selector => [
                ['value' => 'image_image'],
              ],
            ],
          ],
        ];
      }

      $element['widget_settings'][$subfield]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for fields'),
        // '#title_display' => 'invisible',
        '#default_value' => $widget_settings[$subfield]['weight'] ?? 0,
        '#weight' => 20,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();

    $summary = [];
    if (!empty($settings['inline'])) {
      $summary[] = $this->t('Display as inline element');
    }

    foreach ($field_settings["columns"] as $subfield => $item) {
      $subfield_type = $item['type'];

      $summary[] = new FormattableMarkup(
        '<b>@subfield - @subfield_type</b>',
        [
          '@subfield' => $field_settings["field_settings"][$subfield]['label'] ?? $subfield,
          '@subfield_type' => strtolower($subfield_type),
        ]
      );
      if (!empty($settings['widget_settings'][$subfield]['type'])) {
        $summary[] = $this->t('Widget: @type', ['@type' => $settings['widget_settings'][$subfield]['type']]);
      }
      if (!empty($settings['widget_settings'][$subfield]['label_display']) && static::isLabelSupported($settings['widget_settings'][$subfield]['type'])) {
        $summary[] = $this->t('Label display: @label', ['@label' => $settings['widget_settings'][$subfield]['label_display']]);
      }
      switch ($settings['widget_settings'][$subfield]['type']) {
        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          if (!empty($settings['widget_settings'][$subfield]['size'])) {
            $summary[] = $this->t('Size: @size', ['@size' => $settings['widget_settings'][$subfield]['size']]);
          }
          if (!empty($settings['widget_settings'][$subfield]['placeholder'])) {
            $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $settings['widget_settings'][$subfield]['placeholder']]);
          }
          break;

        case 'checkbox':
          if (!empty($settings['widget_settings'][$subfield]['label'])) {
            $summary[] = $this->t('Label: @label', ['@label' => $settings['widget_settings'][$subfield]['label']]);
          }
          break;

        case 'select':
          break;

        case 'textarea':
          if (!empty($settings['widget_settings'][$subfield]['cols'])) {
            $summary[] = $this->t('Columns: @cols', ['@cols' => $settings['widget_settings'][$subfield]['cols']]);
          }
          if (!empty($settings['widget_settings'][$subfield]['rows'])) {
            $summary[] = $this->t('Rows: @rows', ['@rows' => $settings['widget_settings'][$subfield]['rows']]);
          }
          if (!empty($settings[$subfield]['placeholder'])) {
            $summary[] = $this->t('Placeholder: @placeholder', ['@placeholder' => $settings['widget_settings'][$subfield]['placeholder']]);
          }
          break;
      }

    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state): array {
    $storage_settings = $this->getFieldSettings();
    foreach ($values as $delta => &$value) {
      foreach ($storage_settings['columns'] as $subfield => $item) {
        if (!empty($value[$subfield]) && is_array($value[$subfield])) {
          if (isset($value[$subfield]['value'])) {
            $value[$subfield] = $value[$subfield]['value'];
          }
          if ($item['type'] == 'file' && is_array($value[$subfield])) {
            $value[$subfield] = current($value[$subfield]);
          }
        }
        if (!is_numeric($values[$delta][$subfield]) && empty($value[$subfield])) {
          $values[$delta][$subfield] = NULL;
        }
        elseif ($value[$subfield] instanceof DrupalDateTime) {
          $date = $value[$subfield];
          $storage_timezone = new \DateTimezone(FieldItem::DATETIME_STORAGE_TIMEZONE);
          $storage_format = $item['datetime_type'] == 'datetime'
            ? FieldItem::DATETIME_DATETIME_STORAGE_FORMAT
            : FieldItem::DATETIME_DATE_STORAGE_FORMAT;

          if ($item['datetime_type'] == 'year') {
            $storage_format = 'Y';
          }
          if ($item['datetime_type'] == 'time') {
            $storage_format = 'H:i:s';
          }
          if ($item['datetime_type'] == 'timestamp') {
            if ($item['type'] == 'date') {
              $storage_format = 'Y-m-d H:i:s';
              $values[$delta][$subfield] = $date->format($storage_format);
            }
            elseif ($item['type'] == 'datetime_iso8601') {
              $values[$delta][$subfield] = $date->setTimezone($storage_timezone)
                ->getTimestamp();
            }
            continue;
          }
          // Before it can be saved, the time entered by the user must be
          // converted to the storage time zone.
          $values[$delta][$subfield] = $date->setTimezone($storage_timezone)
            ->format($storage_format);
        }
      }
    }

    return $values;
  }

  /**
   * {@inheritDoc}
   */
  public static function convertWidgetType($storage_setting) {
    $type = 'textfield';

    switch ($storage_setting['type']) {
      case 'boolean':
        $type = 'checkbox';
        break;

      case 'json':
      case 'text':
        $type = 'textarea';
        break;

      case 'float':
      case 'integer':
      case 'numeric':
        $type = 'number';
        break;

      case 'datetime_iso8601':
        $type = 'date';
        if (!empty($storage_setting["datetime_type"])) {
          $type = $storage_setting["datetime_type"];
          if ($type == 'timespan') {
            $type = 'datetime_default';
          }
          if ($type == 'time') {
            $type = 'time';
          }
        }
        break;

      case 'date':
        if ($storage_setting["datetime_type"] == 'datetime') {
          $type = 'datetime';
        }
        if ($storage_setting["datetime_type"] == 'date') {
          $type = 'date';
        }
        break;

      case 'email':
        $type = 'email';
        break;

      case 'telephone':
        $type = 'tel';
        break;

      case 'uri':
        $type = 'url';
        break;

      case 'hidden':
        $type = 'hidden';
        break;

      case 'entity_reference':
        $type = 'entity_reference_autocomplete';
        break;

      case 'file':
      case 'blob':
        $type = 'file';
        break;
    }

    return $type;
  }

  /**
   * Returns available subwidgets.
   */
  public function getSubwidgets($subfield_type, bool $list = FALSE, $datetime_type = FALSE): array {
    $subwidgets = [];

    if (!empty($list)) {
      $subwidgets['select'] = $this->t('Select list');
      $subwidgets['radios'] = $this->t('Radio buttons');
    }

    switch ($subfield_type) {

      case 'boolean':
        $subwidgets['checkbox'] = $this->t('Checkbox');
        break;

      case 'string':
        $subwidgets['textfield'] = $this->t('Textfield');
        $subwidgets['email'] = $this->t('Email');
        $subwidgets['tel'] = $this->t('Telephone');
        $subwidgets['url'] = $this->t('Url');
        $subwidgets['color'] = $this->t('Color');
        $subwidgets['search'] = $this->t('Search');
        $subwidgets['hidden'] = $this->t('Hidden');
        $subwidgets['password'] = $this->t('Password');
        break;

      case 'email':
        $subwidgets['email'] = $this->t('Email');
        $subwidgets['textfield'] = $this->t('Textfield');
        break;

      case 'telephone':
        $subwidgets['tel'] = $this->t('Telephone');
        $subwidgets['textfield'] = $this->t('Textfield');
        break;

      case 'uri':
        $subwidgets['url'] = $this->t('Url');
        $subwidgets['textfield'] = $this->t('Textfield');
        $subwidgets['path'] = $this->t('Path');
        break;

      case 'text':
        $subwidgets['textarea'] = $this->t('Text area');
        $subwidgets['text_format'] = $this->t("Text (formatted, long)");
        break;

      case 'integer':
      case 'float':
      case 'numeric':
        $subwidgets['number'] = $this->t('Number');
        $subwidgets['textfield'] = $this->t('Textfield');
        $subwidgets['range'] = $this->t('Range');
        break;

      case 'json':
        $subwidgets['textarea'] = $this->t('Text area');
        $subwidgets['json'] = $this->t('Json editor');
        break;

      case 'date':
      case 'datetime_iso8601':
        $subwidgets['datetime'] = $this->t('Date time');
        if ($datetime_type == 'date') {
          $subwidgets['date'] = $this->t('Date only');
        }
        if ($datetime_type == 'time') {
          $subwidgets['time'] = $this->t('Time');
        }
        if ($datetime_type == 'week') {
          $subwidgets['week'] = $this->t('Week');
        }
        if ($datetime_type == 'month') {
          $subwidgets['month_year'] = $this->t('Month year');
        }
        if ($datetime_type == 'year') {
          $subwidgets['year'] = $this->t('Year');
        }
        if ($datetime_type == 'timestamp') {
          $subwidgets['timestamp'] = $this->t('Timestamp');
        }
        break;

      case 'entity_reference':
        $pluginsWidget = $this->pluginManager->getOptions('entity_reference');
        foreach ($pluginsWidget as $option => $label) {
          // $plugin_class = DefaultFactory::getPluginClass($option,
          // $pluginManager->getDefinition($option));
          // it must be check isApplicable for entity reference.
          $subwidgets[$option] = $label;
        }
        break;

      case 'file':
        $subwidgets['managed_file'] = $this->t('Uploading and saving file');
        $subwidgets['image_image'] = $this->t('Image');
        if ($this->moduleHandler->moduleExists('media_library')) {
          $subwidgets['media_library'] = $this->t('Media library');
        }
        break;

      case 'blob':
        $subwidgets['file'] = $this->t('File');
        $subwidgets['textarea'] = $this->t('Text area');
        $subwidgets['hidden'] = $this->t('Hidden');
        break;

      case 'uuid':
        $subwidgets['uuid'] = $this->t('Universally unique identifiers');
        break;

    }
    if ($subfield_type == 'date' && $datetime_type) {
      switch ($datetime_type) {
        case 'timestamp':
          $subwidgets = [
            'timestamp' => $this->t('Timestamp'),
          ];
          break;
      }
    }

    return $subwidgets;
  }

  /**
   * Determines whether widget can render subfield label.
   */
  public static function isLabelSupported(string $widget_type = NULL): bool {
    return $widget_type != 'checkbox';
  }

  /**
   * {@inheritdoc}
   */
  public function errorElement(array $element, ConstraintViolationInterface $violation, array $form, FormStateInterface $form_state) {
    /* @noinspection PhpUndefinedFieldInspection */
    // @see https://www.drupal.org/project/drupal/issues/2600790
    return isset($violation->arrayPropertyPath[0]) ? $element[$violation->arrayPropertyPath[0]] : $element;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldSettings(): array {
    $field_settings = parent::getFieldSettings();
    foreach ($field_settings["columns"] as $subfield => $item) {
      if (!empty($field_settings["field_settings"][$subfield]['list']) && !FieldItem::isListAllowed($item['type'])) {
        $field_settings["field_settings"][$subfield]['list'] = FALSE;
      }
    }

    return $field_settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettings(): array {
    $settings = parent::getSettings();
    $field_settings = $this->getFieldSettings();
    foreach ($field_settings['columns'] as $subfield => $item) {
      $widget_types = $this->getSubwidgets($item['type'], $field_settings["field_settings"][$subfield]['list'] ?? FALSE, $item["datetime_type"] ?? FALSE);
      // Use the first eligible widget type unless it is set explicitly.
      if (empty($settings['widget_settings'][$subfield]['type'])) {
        $settings['widget_settings'][$subfield]['type'] = key($widget_types);
      }
    }

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field_settings = $this->getFieldSetting('field_settings');
    $settings = $this->getSettings();
    $subfields = array_keys($storage = $this->getFieldSetting("columns"));
    $field = $items[0]->getFieldDefinition();
    $field_widget_default = $field->getDefaultValueLiteral();

    if (!empty($setting = $settings['widget_settings'])) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = array_keys($setting);
    }
    $widget = [];
    $widget_settings_default = [
      'type' => NULL,
      'label_display' => 'block',
      'field_display' => TRUE,
      'size' => 30,
      'placeholder' => '',
      'label' => $this->t('Ok'),
      'cols' => 10,
      'rows' => 5,
    ];
    foreach ($subfields as $subfield) {
      if (empty($storage[$subfield])) {
        continue;
      }
      if (!empty($settings["widget_settings"][$subfield]) && count($settings["widget_settings"][$subfield]) <= 1) {
        $settings["widget_settings"][$subfield] = self::widgetDefault();
      }
      // $item = $storage[$subfield];
      $field_default = !empty($field_widget_default[$delta]) ? $field_widget_default[$delta][$subfield] : '';
      $default_value = $items[$delta]->{$subfield} ?? $field_default;
      $settings["widget_settings"][$subfield] += $widget_settings_default;
      $widget_type = $settings["widget_settings"][$subfield]["type"];
      $widget[$delta][$subfield] = [
        '#type' => $widget_type ?? $this->convertWidgetType($storage[$subfield]),
        '#default_value' => $default_value,
        '#subfield_settings' => $settings["widget_settings"][$subfield],
        '#wrapper_attributes' => ['class' => [Html::getId('data_field-subfield-form-item')]],
      ];
      if ($storage[$subfield]['type'] == 'datetime_iso8601') {
        if (!empty($field_settings[$subfield]["min"])) {
          $widget[$delta][$subfield]["#min"] = $field_settings[$subfield]["min"];
          if ($settings["widget_settings"][$subfield]["type"] == 'week') {
            $widget[$delta][$subfield]["#min"] = date('Y-\WW', strtotime($field_settings[$subfield]["min"]));
          }
          if ($settings["widget_settings"][$subfield]["type"] == 'month_year') {
            $widget[$delta][$subfield]["#min"] = date('Y-m', strtotime($field_settings[$subfield]["min"]));
          }
          if ($settings["widget_settings"][$subfield]["type"] == 'time') {
            $widget[$delta][$subfield]["#min"] = $field_settings[$subfield]["min"];
          }
        }
        if (!empty($field_settings[$subfield]["max"])) {
          $widget[$delta][$subfield]["#max"] = $field_settings[$subfield]["max"];
          if ($settings["widget_settings"][$subfield]["type"] == 'week') {
            $widget[$delta][$subfield]["#max"] = date('Y-\WW', strtotime($field_settings[$subfield]["max"]));
          }
          if ($settings["widget_settings"][$subfield]["type"] == 'month_year') {
            $widget[$delta][$subfield]["#max"] = date('Y-m', strtotime($field_settings[$subfield]["max"]));
          }
          if ($settings["widget_settings"][$subfield]["type"] == 'time') {
            $widget[$delta][$subfield]["#max"] = $field_settings[$subfield]["max"];
          }
        }
      }
      if ($storage[$subfield]['type'] == 'entity_reference' && !empty($field_settings[$subfield]["entity_reference_type"])) {
        $explode = explode(':', $field_settings[$subfield]["entity_reference_type"]);
        $reference_type = end($explode);
        $widget[$delta][$subfield]['#target_type'] = $reference_type;
        $widget[$delta][$subfield] = [
          '#type' => $widget_type ?? $this->convertWidgetType($storage[$subfield]),
          '#default_value' => $default_value,
        ];
        switch ($widget_type) {
          case 'options_buttons':
          case 'options_select':
            $entity_storage = $this->entityTypeManager->getStorage($reference_type);
            if ($reference_type == 'taxonomy_term' && !empty($field_settings[$subfield]["target_bundles"])) {
              $entities = $entity_storage->loadByProperties([
                'vid' => $field_settings[$subfield]["target_bundles"],
              ]);
            }
            elseif ($reference_type == 'user') {
              $entities = $entity_storage->loadByProperties([
                'status' => 1,
              ]);
            }
            elseif (!empty($field_settings[$subfield]["target_bundles"])) {
              $entities = $entity_storage->loadByProperties(['type' => $field_settings[$subfield]["target_bundles"]]);
            }
            $options = [];
            if (!empty($entities)) {
              foreach ($entities as $id => $entity) {
                switch ($reference_type) {
                  case 'user':
                    $options[$id] = $entity->getDisplayName();
                    break;

                  case 'taxonomy_term':
                    $options[$id] = $entity->getName();
                    break;

                  default:
                    $options[$id] = $entity->getTitle();
                    break;

                }
              }
            }
            $widget[$delta][$subfield]['#type'] = $widget_type == 'options_select' ? 'select' : 'radios';
            $widget[$delta][$subfield]['#options'] = $options;
            $widget[$delta][$subfield]['#empty_option'] = $this->t('- Select -');
            break;

          case 'entity_reference_autocomplete':
          case 'entity_reference_autocomplete_tags':
            if (is_numeric($default_value)) {
              $default_value = $this->entityTypeManager->getStorage($reference_type)
                ->load($default_value);
            }
            $widget[$delta][$subfield]['#default_value'] = $default_value;
            $widget[$delta][$subfield]['#type'] = 'entity_autocomplete';
            $widget[$delta][$subfield]['#maxlength'] = 1024;
            $widget[$delta][$subfield]['#tag'] = $widget_type == 'entity_reference_autocomplete_tags';
            $widget[$delta][$subfield]['#placeholder'] = implode(' ', [
              $this->t('Typing'),
              $field_settings[$subfield]["label"],
            ]);
            $widget[$delta][$subfield]['#target_type'] = $reference_type;
            $widget[$delta][$subfield]['#selection_handler'] = 'default';
            if ($reference_type == 'user') {
              $widget[$delta][$subfield]['#selection_settings'] = [
                'include_anonymous' => FALSE,
              ];
            }
            else {
              $widget[$delta][$subfield]['#autocreate'] = [
                'bundle' => $bundle = $field_settings[$subfield]["target_bundles"],
              ];
              $widget[$delta][$subfield]['#selection_settings'] = [
                'target_bundles' => [$bundle],
              ];
            }
            break;

          default:
            if ($reference_type == 'taxonomy_term' && !empty($field_settings[$subfield]["target_bundles"])) {
              $widget[$delta][$subfield]['#type'] = 'select';
              $widget[$delta][$subfield]['#options'] = $this->loadVoc($field_settings[$subfield]["target_bundles"]);
              $widget[$delta][$subfield]['#empty_option'] = $this->t('- Select -');
            }
            break;
        }
      }

      switch ($widget_type) {

        case 'textfield':
        case 'email':
        case 'tel':
        case 'url':
          // Find out appropriate max length fot the element.
          $max_length_map = [
            'string' => $storage[$subfield]['max_length'],
            'telephone' => $storage[$subfield]['max_length'],
            'email' => Email::EMAIL_MAX_LENGTH,
            'uri' => 2048,
          ];
          if (isset($max_length_map[$widget_type])) {
            $widget[$delta][$subfield]['#maxlength'] = $max_length_map[$widget_type];
          }
          if (!empty($settings["widget_settings"][$subfield]['size'])) {
            $widget[$delta][$subfield]['#size'] = $settings["widget_settings"][$subfield]['size'];
          }
          if (!empty($settings["widget_settings"][$subfield]['placeholder'])) {
            $widget[$delta][$subfield]['#placeholder'] = $settings["widget_settings"][$subfield]['placeholder'];
          }
          break;

        case 'checkbox':
          $widget[$delta][$subfield]['#title'] = $settings["widget_settings"][$subfield]['label'];
          break;

        case 'select':
          $label = $field_settings[$subfield]['required'] ? $this->t('- Select a value -') : $this->t('- None -');
          $widget[$delta][$subfield]['#options'] = ['' => $label];
          if ($field_settings[$subfield]['list']) {
            $widget[$delta][$subfield]['#options'] += $field_settings[$subfield]['allowed_values'];
          }
          break;

        case 'radios':
          $label = $field_settings[$subfield]['required'] ? $this->t('N/A') : $this->t('- None -');
          $widget[$delta][$subfield]['#options'] = ['' => $label];
          if ($field_settings[$subfield]['list']) {
            $widget[$delta][$subfield]['#options'] += $field_settings[$subfield]['allowed_values'];
          }
          break;

        case 'textarea':
          if ($settings["widget_settings"][$subfield]['rows']) {
            $widget[$delta][$subfield]['#rows'] = $settings["widget_settings"][$subfield]['rows'];
          }
          if ($settings["widget_settings"][$subfield]['placeholder']) {
            $widget[$delta][$subfield]['#placeholder'] = $settings["widget_settings"][$subfield]['placeholder'];
          }
          break;

        case 'number':
        case 'range':
          if (in_array($storage[$subfield]["type"], [
            'integer',
            'float',
            'numeric',
          ])) {
            $widget[$delta][$subfield]['#step'] = 1;
            if (!empty($field_settings[$subfield]['min'])) {
              $widget[$delta][$subfield]['#min'] = $field_settings[$subfield]['min'];
            }
            if (!empty($field_settings[$subfield]['max'])) {
              $widget[$delta][$subfield]['#max'] = $field_settings[$subfield]['max'];
            }
            if ($storage[$subfield]["type"] == 'numeric') {
              $widget[$delta][$subfield]['#step'] = 0.1;
              if (!empty($storage[$subfield]['scale'])) {
                $widget[$delta][$subfield]['#step'] = pow(0.1, $storage[$subfield]['scale']);
              }
            }
            elseif ($storage[$subfield]["type"] == 'float') {
              $widget[$delta][$subfield]['#step'] = 'any';
            }
          }
          break;

        case 'date':
        case 'datetime':
          $widget[$delta][$subfield]['#default_value'] = $items[$delta]->createDate($subfield);
          if ($storage[$subfield]['datetime_type'] == 'date') {
            $widget[$delta][$subfield]['#date_time_element'] = 'none';
            $widget[$delta][$subfield]['#date_time_format'] = '';
            if ($storage[$subfield]["type"] != 'date') {
              $widget[$delta][$subfield]['#default_value'] = $items[$delta]->{$subfield};
            }
          }
          elseif ($storage[$subfield]['datetime_type'] == 'timestamp') {
            $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
          }
          else {
            if (!empty($widget[$subfield]['#default_value'])) {
              $widget[$delta][$subfield]['#default_value']->setTimezone(new \DateTimezone(date_default_timezone_get()));
            }
            // Ensure that the datetime field processing doesn't set its own
            // time zone here.
            $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
          }
          break;

        case 'time':
          if ($storage[$subfield]['type'] == 'datetime_iso8601') {
            $widget[$delta][$subfield]['#type'] = 'date';
            $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
            $widget[$delta][$subfield]['#attributes']['type'] = 'time';
          }
          else {
            $widget[$delta][$subfield]['#default_value'] = $items[$delta]->createDate($subfield);
            $widget[$delta][$subfield]['#type'] = 'datetime';
            $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
            $widget[$delta][$subfield]['#date_date_element'] = 'none';
            $widget[$delta][$subfield]['#date_time_element'] = 'time';
            $widget[$delta][$subfield]['#date_time_format'] = 'H:i';
          }
          break;

        case 'timestamp':
          $widget[$delta][$subfield]['#default_value'] = $items[$delta]->createDate($subfield);
          $widget[$delta][$subfield]['#type'] = 'datetime';
          $widget[$delta][$subfield]['#date_time_format'] = 'Y-m-d H:i:s';
          break;

        case 'week':
          $widget[$delta][$subfield]['#type'] = 'date';
          $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
          $widget[$delta][$subfield]['#attributes']['type'] = 'week';
          break;

        case 'month_year':
          $widget[$delta][$subfield]['#type'] = 'date';
          $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
          $widget[$delta][$subfield]['#date_date_format'] = 'm/Y';
          $widget[$delta][$subfield]['#date_date_element'] = 'month';
          $widget[$delta][$subfield]['#date_time_element'] = 'none';
          $widget[$delta][$subfield]['#attributes']['type'] = 'month';
          break;

        case 'year':
          $widget[$delta][$subfield]['#type'] = 'datelist';
          $widget[$delta][$subfield]['#date_timezone'] = date_default_timezone_get();
          $widget[$delta][$subfield]['#date_part_order'] = ['year'];
          $widget[$delta][$subfield]['#date_date_format'] = 'Y';
          $widget[$delta][$subfield]['#date_date_element'] = 'year';
          $widget[$delta][$subfield]['#date_time_element'] = 'none';
          $widget[$delta][$subfield]['#date_year_range'] = date('Y') - 20 . ':' . (date('Y') + 10);
          if (!empty($field_settings[$subfield]["min"]) && !empty($field_settings[$subfield]["max"])) {
            $widget[$delta][$subfield]['#date_year_range'] = date('Y', strtotime($field_settings[$subfield]["min"])) . ':' . date('Y', strtotime($field_settings[$subfield]["max"]));
          }
          if ($storage[$subfield]['type'] == 'date') {
            if (!is_object($widget[$delta][$subfield]["#default_value"])) {
              $widget[$delta][$subfield]['#default_value'] = $items[$delta]->createDate($subfield);
            }
          }
          elseif (!empty($items[$delta]->{$subfield})) {
            $widget[$delta][$subfield]['#default_value'] = DrupalDateTime::createFromFormat('Y', $items[$delta]->{$subfield});
          }
          break;

        case 'managed_file':
        case 'image_image':
        case 'media_library':
          $fieldName = str_replace('field_', '', $this->fieldDefinition->getName());
          $widget[$delta][$subfield]['#upload_location'] = 'public://' . $fieldName . '/';
          if (!empty($items[$delta]->{$subfield})) {
            $widget[$delta][$subfield]["#default_value"] = [$items[$delta]->{$subfield}];
          }
          if (!empty($field_settings[$subfield]["file_directory"])) {
            $widget[$delta][$subfield]['#upload_location'] = 'public://' . $field_settings[$subfield]["file_directory"] . '/';
          }
          if (!empty($field_settings[$subfield]["file_extensions"])) {
            $widget[$delta][$subfield]['#upload_validators'] = [
              'file_validate_extensions' => [$field_settings[$subfield]["file_extensions"]],
            ];
          }
          if ($widget_type == 'image_image') {
            $widget[$delta][$subfield]["#type"] = 'managed_file';
            $widget[$delta][$subfield]["#upload_validators"] = [
              'file_validate_extensions' => ['gif png jpg jpeg'],
            ];
            $preview = $settings["widget_settings"][$subfield]["preview_image_style"];
            $widget[$delta][$subfield]['#theme'] = 'image_widget';
            $widget[$delta][$subfield]['#preview_image_style'] = $preview ?? 'medium';
          }
          if ($widget_type == 'media_library') {
            $widget[$delta][$subfield] = [
              '#type' => 'entity_autocomplete',
              '#target_type' => 'media',
              "#default_value" => [$items[$delta]->{$subfield}],
              '#allowed_bundles' => [
                'audio', 'document', 'image',
                'video', 'remote_video',
              ],
            ];
            if ($this->moduleHandler->moduleExists('media_library_form_element')) {
              $widget[$delta][$subfield]['#type'] = 'media_library';
            }
          }
          break;

        case 'json':
          $id = Html::getUniqueId($items->getName() . '-' . $delta);
          $widget[$delta][$subfield]["#type"] = 'textarea';
          $widget[$delta][$subfield]['#attached']['library'] = ['datafield/json_editor'];
          $widget[$delta][$subfield]['#description'] = '<div id="' . $id . '"></div>';
          $widget[$delta][$subfield]['#attributes'] = [
            'data-json-editor' => $this->getSetting('mode'),
            'data-id' => $id,
            'class' => ['json-editor', 'js-hide', $this->getSetting('mode')],
          ];
          break;
      }
    }
    return $widget;
  }

  /**
   * Loads the tree of a vocabulary.
   *
   * {@inheritdoc}
   */
  public function loadVoc($vocabulary) {
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vocabulary);
    $tree = [];
    foreach ($terms as $tree_object) {
      $this->buildTree($tree, $tree_object, $vocabulary);
    }

    return $tree;
  }

  /**
   * Populates a tree array given a taxonomy term tree object.
   *
   * {@inheritdoc}
   */
  protected function buildTree(&$tree, $object, $vocabulary, $level = 1) {
    if ($object->depth != 0 || $object->status == 0) {
      return;
    }
    $tree[$object->tid] = $object->name;
    $children = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadChildren($object->tid);
    if (!$children) {
      return;
    }

    $child_tree_objects = $this->entityTypeManager->getStorage('taxonomy_term')
      ->loadTree($vocabulary, $object->tid);

    foreach ($children as $child) {
      foreach ($child_tree_objects as $child_tree_object) {
        if ($child_tree_object->tid == $child->id()) {
          $child_tree_object->name = str_repeat('-', $level) . $child_tree_object->name;
          $this->buildTree($tree, $child_tree_object, $vocabulary, $level + 1);
        }
      }
    }
  }

}
