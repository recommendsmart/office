<?php

namespace Drupal\datafield\Plugin\Field\FieldType;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Utility\Random;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\Email;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\views\Views;

/**
 * Plugin implementation of the 'data_field' field type.
 *
 * @FieldType(
 *   id = "data_field",
 *   label = @Translation("Data Field"),
 *   description = @Translation("Data Field."),
 *   default_widget = "data_field_table_widget",
 *   default_formatter = "data_field_table_formatter"
 * )
 */
class DataField extends FieldItemBase {

  public const DATETIME_STORAGE_TIMEZONE = 'UTC';

  public const DATETIME_DATETIME_STORAGE_FORMAT = 'Y-m-d\TH:i:s';

  public const DATETIME_DATE_STORAGE_FORMAT = 'Y-m-d';

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    // Need to have at least one item by default because the table is created
    // before the user gets a chance to customize and will throw an Exception
    // if there isn't at least one column defined.
    return [
      'columns' => [
        'value' => [
          'name' => 'value',
          'max_length' => 255,
          'size' => 'normal',
          'type' => 'string',
          'unsigned' => FALSE,
          'precision' => 10,
          'scale' => 2,
          'datetime_type' => 'date',
        ],
      ],
    ] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $settings = $this->getSettings();
    $default_settings = self::defaultStorageSettings()['columns']['value'];
    if ($form_state->isRebuilding()) {
      $settings = $form_state->getValue('settings');
    }
    else {
      $settings['storage'] = $settings['columns'];
    }

    // Add a new item if there aren't any or we're rebuilding.
    if ($form_state->get('add') || count($settings['columns']) == 0) {
      $settings['columns'][] = current($settings['columns']);
      $form_state->set('add', NULL);
    }

    $wrapper_id = 'datafield-items-wrapper';
    $element = [
      '#tree' => TRUE,
      'columns' => [
        '#type' => 'value',
        '#value' => $settings['columns'],
      ],
      'storage' => [
        '#type' => 'table',
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#header' => [
          $this->t('Machine-readable name'),
          $this->t('Type'),
          $this->t('Maximum length'),
          $this->t('Unsigned'),
          $this->t('Size'),
          $this->t('Precision'),
          $this->t('Scale'),
          $this->t('Date type'),
          '',
        ],
      ],
      'actions' => [
        '#type' => 'actions',
      ],
    ];
    $fieldName = $this->getFieldDefinition()->getName();
    $lengthField = strlen($fieldName);
    $i = 0;
    foreach ($settings['columns'] as $subfield => $item) {
      if ($i === $form_state->get('remove')) {
        $form_state->set('remove', NULL);
        // @todo it must remove columns in database.
        if (!empty($settings["columns"][$subfield])) {
          unset($settings["columns"][$subfield]);
        }
        if (!empty($element["columns"]["#value"][$subfield])) {
          unset($element["columns"]["#value"][$subfield]);
        }
        if (!empty($element["columns"]["#value"][$subfield])) {
          unset($element["columns"]["#value"][$subfield]);
        }
        if (!empty($element["storage"][$i])) {
          unset($element["storage"][$i]);
        }
        continue;
      }

      $element['storage'][$i]['name'] = [
        '#type' => 'machine_name',
        '#description' => $this->t('A unique machine-readable name containing only letters, numbers, or underscores. This will be used in the column name on the field table in the database.'),
        '#default_value' => $item['name'] ?? uniqid('value_'),
        '#disabled' => $has_data,
        '#maxlength' => 63 - $lengthField,
        '#required' => TRUE,
        '#title_display' => 'invisible',
        '#attributes' => [
          'pattern' => '[a-z_0-9]+',
        ],
        '#machine_name' => [
          'exists' => [$this, 'machineNameExists'],
          'standalone' => TRUE,
        ],
      ];

      $element['storage'][$i]['type'] = [
        '#type' => 'select',
        '#default_value' => $item['type'],
        '#disabled' => $has_data,
        '#required' => TRUE,
        '#options' => $this->subfieldTypes(),
      ];

      $element['storage'][$i]['max_length'] = [
        '#type' => 'number',
        '#description' => $this->t('The maximum length of the subfield in characters.'),
        '#default_value' => $item['max_length'] ?? $default_settings["max_length"],
        '#disabled' => $has_data,
        '#min' => 1,
        '#states' => [
          'visible' => [
            ":input[name='settings[storage][$i][type]']" => [
              ['value' => 'string'],
              ['value' => 'char'],
              ['value' => 'varchar'],
            ],
          ],
        ],
      ];

      $element['storage'][$i]['unsigned'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Unsigned'),
        '#default_value' => $item['unsigned'] ?? $default_settings['unsigned'],
        '#disabled' => $has_data,
        '#states' => [
          'visible' => [
            ':input[name="settings[storage][' . $i . '][type]"]' => [
              ['value' => 'integer'],
              ['value' => 'float'],
              ['value' => 'numeric'],
            ],
          ],
        ],
      ];

      $element['storage'][$i]['size'] = [
        '#type' => 'select',
        '#default_value' => $item['size'] ?? $default_settings['size'],
        '#disabled' => $has_data,
        '#options' => [
          'normal' => $this->t('Normal'),
          'big' => $this->t('Big'),
          'medium' => $this->t('Medium'),
          'small' => $this->t('Small'),
          'tiny' => $this->t('Tiny'),
        ],
        '#states' => [
          'visible' => [
            ':input[name="settings[storage][' . $i . '][type]"]' => [
              ['value' => 'integer'],
              ['value' => 'serial'],
              ['value' => 'float'],
              ['value' => 'text'],
              ['value' => 'blob'],
            ],
          ],
        ],
      ];

      $element['storage'][$i]['precision'] = [
        '#type' => 'number',
        '#description' => $this->t('The total number of digits to store in the database, including those to the right of the decimal.'),
        '#default_value' => $item['precision'] ?? $default_settings['precision'],
        '#disabled' => $has_data,
        '#min' => 10,
        '#max' => 32,
        '#states' => [
          'visible' => [
            ":input[name='settings[storage][$i][type]']" => [
              ['value' => 'numeric'],
              ['value' => 'float'],
            ],
          ],
        ],
      ];

      $element['storage'][$i]['scale'] = [
        '#type' => 'number',
        '#description' => $this->t('The number of digits to the right of the decimal.'),
        '#default_value' => $item['scale'] ?? $default_settings['scale'],
        '#disabled' => $has_data,
        '#min' => 0,
        '#max' => 10,
        '#states' => [
          'visible' => [":input[name='settings[storage][$i][type]']" => ['value' => 'numeric']],
        ],
      ];

      $element['storage'][$i]['datetime_type'] = [
        '#type' => 'radios',
        '#description' => $this->t('Choose the type of date to create.'),
        '#default_value' => $item['datetime_type'] ?? $default_settings['datetime_type'],
        '#disabled' => $has_data,
        '#options' => [
          'datetime' => $this->t('Date and time'),
          'date' => $this->t('Date'),
          'time' => $this->t('Time'),
          'year' => $this->t('Year'),
          'month' => $this->t('Month'),
          'week' => $this->t('Week'),
          'timestamp' => $this->t('Timestamp'),
        ],
        '#states' => [
          'visible' => [
            ":input[name='settings[storage][$i][type]']" => [
              ['value' => 'datetime_iso8601'],
              ['value' => 'date'],
            ],
          ],
        ],
      ];

      $element['storage'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#submit' => [get_class($this) . '::removeSubmit'],
        '#name' => 'remove:' . $i,
        '#delta' => $i,
        '#disabled' => $has_data,
        '#ajax' => [
          'callback' => [$this, 'actionCallback'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $i++;
    }

    if (!$has_data) {
      $element['actions']['add'] = [
        '#type' => 'submit',
        '#value' => $this->t('Add another'),
        '#submit' => [get_class($this) . '::addSubmit'],
        '#ajax' => [
          'callback' => [$this, 'actionCallback'],
          'wrapper' => $wrapper_id,
        ],
        // @todo copy setting.
        '#states' => [
          'visible' => [
            'select[data-id="datafield-settings-clone"]' => ['value' => ''],
          ],
        ],
      ];
    }

    $form_state->setCached(FALSE);
    return $element;
  }

  /**
   * Submit handler for the StorageConfigEditForm.
   *
   * This handler is added in flexfield.module since it has to be placed
   * directly on the submit button (which we don't have access to in our
   * ::storageSettingsForm() method above).
   */
  public static function submitStorageConfigEditForm(array &$form, FormStateInterface $form_state) {
    // Rekey our column settings and overwrite the values in form_state so that
    // we have clean settings saved to the db.
    $columns = [];
    foreach ($form_state->getValue(['settings', 'storage']) as $item) {
      $columns[$item['name']] = $item;
      unset($columns[$item['name']]['remove']);
    }
    $form_state->setValue(['settings', 'columns'], $columns);
    $form_state->setValue(['settings', 'storage'], NULL);

    // Reset the field storage config property -
    // it will be recalculated when accessed via the property definition getter.
    // @see Drupal\field\Entity\FieldStorageConfig::getPropertyDefinitions()
    // If we don't do this, an exception is thrown during the table update that
    // is very difficult to recover from since the original field tables have
    // already been removed at that point.
    $field_storage_config = $form_state->getBuildInfo()['callback_object']->getEntity();
    $field_storage_config->set('propertyDefinitions', NULL);
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    return [
      'field_settings' => [],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $field_setting_default = [
      'label' => '',
      'min' => '',
      'max' => '',
      'list' => FALSE,
      'allowed_values' => [],
      'required' => FALSE,
      'on_label' => $this->t('On'),
      'off_label' => $this->t('Off'),
      'entity_reference_type' => '',
      'target_bundles' => '',
      'view_arguments' => '',
      'file_directory' => '',
      'file_extensions' => 'jpg jpeg gif png txt doc xls pdf ppt pps odt ods odp',
    ];
    if ($form_state->isRebuilding()) {
      $formSettings = $form_state->getValue('settings');
      if (empty($formSettings)) {
        $formSettings = $form_state->getUserInput()['settings'];
      }
      $field_settings = $formSettings['field_settings'] ?? [];
      $settings['field_settings'] = $field_settings;
    }
    else {
      $field_settings = $this->getSetting('field_settings');
    }
    if (!empty($settings["field_settings"])) {
      unset($settings["field_settings"]);
    }
    $types = static::subfieldTypes();
    $element = [
      '#type' => 'fieldset',
      '#title' => $this->t('Data Items'),
    ];
    $repository = \Drupal::service('entity_type.repository')->getEntityTypeLabels(TRUE);
    $bundleInfo = \Drupal::service('entity_type.bundle.info');
    $entityTypeManager = \Drupal::entityTypeManager();
    $contentType = $repository['Content'] ?? [];
    $contentType['image'] = $this->t('Image');
    $view_storage = $entityTypeManager->getStorage('view');
    $displaysViewsRef = Views::getApplicableViews('entity_reference_display');
    foreach ($settings["columns"] as $subfield => $item) {
      if (empty($field_settings[$subfield])) {
        $field_settings[$subfield] = $field_setting_default;
      }
      if (empty($field_settings[$subfield]['label'])) {
        $field_settings[$subfield]['label'] = str_replace('_', ' ', $subfield);
      }
      $type = $item['type'];
      $title = $field_settings[$subfield]['label'] ?? ucfirst($subfield);
      $title .= ' - ' . $types[$type];

      $element['field_settings'][$subfield] = [
        '#type' => 'details',
        '#title' => $title,
        '#open' => FALSE,
        '#tree' => TRUE,
      ];

      $element['field_settings'][$subfield]['label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Label'),
        '#default_value' => $field_settings[$subfield]['label'],
      ];

      $element['field_settings'][$subfield]['required'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Required'),
        '#default_value' => $field_settings[$subfield]['required'],
      ];

      if (!empty($type) && static::isListAllowed($type)) {
        $element['field_settings'][$subfield]['list'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Limit allowed values'),
          '#default_value' => $field_settings[$subfield]['list'],
        ];

        $description = [$this->t('The possible values this field can contain. Enter one value per line, in the format key|label.')];
        $description[] = $this->t('The label will be used in displayed values and edit forms.');
        $description[] = $this->t('The label is optional: if a line contains a single item, it will be used as key and label.');

        $element['field_settings'][$subfield]['allowed_values'] = [
          '#type' => 'textarea',
          '#title' => $this->t('Allowed values list'),
          '#description' => implode('<br/>', $description),
          '#default_value' => !empty($field_settings[$subfield]['allowed_values']) ? $this->allowedValuesString($field_settings[$subfield]['allowed_values']) : '',
          '#rows' => 10,
          '#element_validate' => [[get_class($this), 'validateAllowedValues']],
          '#storage_type' => $type,
          '#storage_max_length' => $item['max_length'],
          '#field_name' => $this->getFieldDefinition()->getName(),
          '#entity_type' => $this->getEntity()->getEntityTypeId(),
          '#allowed_values' => $field_settings[$subfield]['allowed_values'],
          '#states' => [
            'invisible' => [":input[name='settings[field_settings][$subfield][list]']" => ['checked' => FALSE]],
          ],
        ];
      }
      else {
        $element['field_settings'][$subfield]['list'] = [
          '#type' => 'value',
          '#default_value' => FALSE,
        ];
        $element['field_settings'][$subfield]['allowed_values'] = [
          '#type' => 'value',
          '#default_value' => [],
        ];
      }

      if (in_array($type, ['integer', 'float', 'numeric', 'datetime_iso8601'])) {
        $element['field_settings'][$subfield]['min'] = [
          '#type' => $type != 'datetime_iso8601' ? 'number' : 'date',
          '#title' => $this->t('Minimum'),
          '#description' => $this->t('The minimum value that should be allowed in this field. Leave blank for no minimum.'),
          '#default_value' => $field_settings[$subfield]['min'] ?? NULL,
          '#states' => [
            'visible' => [":input[name='settings[field_settings][$subfield][list]']" => ['checked' => FALSE]],
          ],
        ];
        $element['field_settings'][$subfield]['max'] = [
          '#type' => $type != 'datetime_iso8601' ? 'number' : 'date',
          '#title' => $this->t('Maximum'),
          '#description' => $this->t('The maximum value that should be allowed in this field. Leave blank for no maximum.'),
          '#default_value' => $field_settings[$subfield]['max'] ?? NULL,
          '#states' => [
            'visible' => [":input[name='settings[field_settings][$subfield][list]']" => ['checked' => FALSE]],
          ],
        ];
        if ($type == 'datetime_iso8601') {
          if (in_array($settings["columns"][$subfield]["datetime_type"], [
            'date',
            'datetime',
          ])) {
            $element['field_settings'][$subfield]['min']['#type'] = $settings["columns"][$subfield]["datetime_type"];
            $element['field_settings'][$subfield]['max']['#type'] = $settings["columns"][$subfield]["datetime_type"];
          }
          if (in_array($settings["columns"][$subfield]["datetime_type"], ['time'])) {
            $element['field_settings'][$subfield]['min']['#type'] = 'date';
            $element['field_settings'][$subfield]['min']['#attributes']['type'] = 'time';
            $element['field_settings'][$subfield]['max']['#type'] = 'date';
            $element['field_settings'][$subfield]['max']['#attributes']['type'] = 'time';
          }
        }
      }
      else {
        $element['field_settings'][$subfield]['min'] = $element['field_settings'][$subfield]['max'] = [
          '#type' => 'value',
          '#default_value' => '',
        ];
      }

      if ($type == 'boolean') {
        $element['field_settings'][$subfield]['on_label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('"On" label'),
          '#default_value' => $field_settings[$subfield]['on_label'],
        ];
        $element['field_settings'][$subfield]['off_label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('"Off" label'),
          '#default_value' => $field_settings[$subfield]['off_label'],
        ];
      }
      else {
        $element['field_settings'][$subfield]['on_label'] = [
          '#type' => 'value',
          '#default_value' => $field_settings[$subfield]['on_label'],
        ];
        $element['field_settings'][$subfield]['off_label'] = [
          '#type' => 'value',
          '#default_value' => $field_settings[$subfield]['off_label'],
        ];
      }

      if ($type == 'entity_reference') {
        $element['field_settings'][$subfield]['entity_reference_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Entity reference type'),
          '#options' => $contentType,
          '#empty_option' => $this->t('- Select a field type -'),
          '#default_value' => $field_settings[$subfield]['entity_reference_type'],
          '#description' => $this->t('Save and back again to select bundle'),
        ];
        if (!empty($entity_reference_type = $field_settings[$subfield]['entity_reference_type'])) {
          $explode = explode(':', $entity_reference_type);
          $entity_type_id = end($explode);
          if ($entity_type_id == 'image') {
            $entity_type_id = 'file';
          }
          $entity_type = $entityTypeManager->getDefinition($entity_type_id);
          $titleBundle = $entity_type->getBundleLabel() ?? $this->t('Wait after select type');
          $bundles = $bundleInfo->getBundleInfo($entity_type_id);
          if (!empty($bundles)) {
            $bundle_options = [];
            foreach ($bundles as $bundle_name => $bundle_info) {
              $bundle_options[$bundle_name] = $bundle_info['label'];
            }
          }
          if (!empty($displaysViewsRef)) {
            // Filter views that list the entity type we want,
            // and group the separate displays by view.
            $optionsViewRef = [];
            foreach ($displaysViewsRef as $data) {
              [$view_id, $display_id] = $data;
              $view = $view_storage->load($view_id);
              if (in_array($view->get('base_table'), [
                $entity_type->getBaseTable(),
                $entity_type->getDataTable(),
              ])) {
                $display = $view->get('display');
                $optionsViewRef[$view_id . ':' . $display_id] = $view_id . ' - ' . $display[$display_id]['display_title'];
              }
            }
            if (!empty($optionsViewRef)) {
              $bundle_options['views'] = $optionsViewRef;
            }
          }
          $element['field_settings'][$subfield]['target_bundles'] = [
            '#type' => 'select',
            '#title' => $titleBundle,
            '#options' => $bundle_options ?? [],
            '#empty_option' => $this->t('- Select a bundle -'),
            '#default_value' => $field_settings[$subfield]['target_bundles'] ?? '',
            '#required' => TRUE,
          ];
          $tmp = explode(':', $element['field_settings'][$subfield]['target_bundles']['#default_value']);
          // Check if the entity type is a view reference.
          if (count($tmp) > 1) {
            $element['field_settings'][$subfield]['view_arguments'] = [
              '#type' => 'textfield',
              '#title' => $this->t('View arguments'),
              '#default_value' => $field_settings[$subfield]['view_arguments'] ?? '',
              '#description' => $this->t('Provide a comma separated list of arguments to pass to the view.'),
            ];
          }
        }
      }

      if ($type == 'file') {
        $element['field_settings'][$subfield]['file_directory'] = [
          '#type' => 'textfield',
          '#title' => $this->t('File directory'),
          '#default_value' => $field_settings[$subfield]['file_directory'],
          '#description' => $this->t('Optional subdirectory within the upload destination where files will be stored. Do not include preceding or trailing slashes.'),
          // '#element_validate' => [[static::class, 'validateDirectory']],
        ];
        $element['field_settings'][$subfield]['file_extensions'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Allowed file extensions'),
          '#default_value' => $field_settings[$subfield]['file_extensions'],
          '#description' => $this->t("Separate extensions with a comma or space. Each extension can contain alphanumeric characters, '.', and '_', and should start and end with an alphanumeric character."),
          // '#element_validate' => [[static::class, 'validateExtensions']],
          '#maxlength' => 256,
          '#required' => TRUE,
        ];
      }

    }

    return $element;
  }

  /**
   * {@inheritdoc}
   *
   * @todo Find a way to disable constraints for default field values.
   */
  public function getConstraints(): array {

    $constraint_manager = \Drupal::typedDataManager()
      ->getValidationConstraintManager();
    $constraints = parent::getConstraints();
    $settings = $this->getSettings();
    $subconstrains = [];
    foreach ($this->getSetting('columns') as $subfield => $item) {

      $subfield_type = $item['type'];

      $is_list = !empty($subfield_type) && !empty($settings['field_settings'][$subfield]) && $settings['field_settings'][$subfield]['list'] && static::isListAllowed($subfield_type);
      if ($is_list && $settings['field_settings'][$subfield]['allowed_values']) {
        $allowed_values = array_keys($settings['field_settings'][$subfield]['allowed_values']);
        $subconstrains[$subfield]['AllowedValues'] = $allowed_values;
      }

      if ($subfield_type == 'string' || $subfield_type == 'telephone') {
        $subconstrains[$subfield]['Length']['max'] = $item['max_length'];
      }

      // Allowed values take precedence over the range constraints.
      $numeric_types = ['integer', 'float', 'numeric'];
      if (!empty($settings['field_settings'][$subfield]) &&
        !empty($settings['field_settings'][$subfield]['list']) &&
        in_array($subfield_type, $numeric_types)) {
        if (is_numeric($settings['field_settings'][$subfield]['min'])) {
          $subconstrains[$subfield]['Range']['min'] = $settings['field_settings'][$subfield]['min'];
        }
        if (is_numeric($settings['field_settings'][$subfield]['max'])) {
          $subconstrains[$subfield]['Range']['max'] = $settings['field_settings'][$subfield]['max'];
        }
      }

      if ($subfield_type == 'email') {
        $subconstrains[$subfield]['Length']['max'] = Email::EMAIL_MAX_LENGTH;
      }

      if (!empty($settings['field_settings'][$subfield]) && $settings['field_settings'][$subfield]['required']) {
        // NotBlank validator is not suitable for booleans because it does not
        // recognize '0' as an empty value.
        if ($subfield_type == 'boolean') {
          $subconstrains[$subfield]['NotEqualTo']['value'] = 0;
          $subconstrains[$subfield]['NotEqualTo']['message'] = $this->t('This value should not be blank.');
        }
        else {
          $subconstrains[$subfield]['NotBlank'] = [];
        }
      }

    }

    $constraints[] = $constraint_manager->create('ComplexData', $subconstrains);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {

    $columns = [];
    foreach ($field_definition->getSetting('columns') as $subfield => $item) {
      if (!empty($item['name'])) {
        $subfield = $item['name'];
      }
      $type = $item['type'];

      $columns[$subfield] = [
        'not null' => FALSE,
        'description' => ucfirst($subfield) . ' subfield value.',
      ];

      switch ($type) {
        case 'string':
        case 'telephone':
          $columns[$subfield]['type'] = 'varchar';
          $columns[$subfield]['length'] = $item['max_length'];
          break;

        case 'text':
          $columns[$subfield]['type'] = 'text';
          $columns[$subfield]['size'] = $item['size'] ?? 'big';
          break;

        case 'file':
        case 'integer':
        case 'entity_reference':
          $columns[$subfield]['type'] = 'int';
          $columns[$subfield]['unsigned'] = $item['unsigned'];
          $columns[$subfield]['size'] = $item['size'] ?? 'normal';
          break;

        case 'serial':
          $columns[$subfield]['type'] = 'serial';
          $columns[$subfield]['unsigned'] = $item['unsigned'];
          $columns[$subfield]['size'] = $item['size'] ?? 'normal';
          $columns[$subfield]['serialize'] = TRUE;
          break;

        case 'float':
          $columns[$subfield]['type'] = 'float';
          $columns[$subfield]['unsigned'] = $item['unsigned'];
          // Big makes the float behaviour less surprising.
          $columns[$subfield]['size'] = $item['size'] ?? 'big';
          break;

        case 'boolean':
          $columns[$subfield]['type'] = 'int';
          $columns[$subfield]['size'] = 'tiny';
          break;

        case 'numeric':
          $columns[$subfield]['type'] = 'numeric';
          $columns[$subfield]['unsigned'] = $item['unsigned'];
          if (!empty($item['precision'])) {
            $columns[$subfield]['precision'] = $item['precision'];
          }
          if (!empty($item['scale'])) {
            $columns[$subfield]['scale'] = $item['scale'];
          }
          break;

        case 'email':
          $columns[$subfield]['type'] = 'varchar';
          $columns[$subfield]['length'] = Email::EMAIL_MAX_LENGTH;
          break;

        case 'uri':
          $columns[$subfield]['type'] = 'varchar';
          $columns[$subfield]['length'] = 2048;
          break;

        case 'datetime_iso8601':
          $columns[$subfield]['type'] = 'varchar';
          $columns[$subfield]['length'] = 26;
          if ($item['datetime_type'] == 'timestamp') {
            $columns[$subfield]['type'] = 'int';
            $columns[$subfield]['unsigned'] = TRUE;
            $columns[$subfield]['length'] = 10;
          }
          break;

        case 'date':
          if ($item['datetime_type'] == 'date') {
            $columns[$subfield]['type'] = 'date';
            $columns[$subfield]['mysql_type'] = 'date';
            $columns[$subfield]['pgsql_type'] = 'date';
            $columns[$subfield]['sqlite_type'] = 'varchar';
            $columns[$subfield]['sqlsrv_type'] = 'date';
          }
          if ($item['datetime_type'] == 'datetime') {
            $columns[$subfield]['type'] = 'datetime';
            $columns[$subfield]['mysql_type'] = 'datetime';
            $columns[$subfield]['pgsql_type'] = 'timestamp without time zone';
            $columns[$subfield]['sqlite_type'] = 'varchar';
            $columns[$subfield]['sqlsrv_type'] = 'smalldatetime';
          }
          if ($item['datetime_type'] == 'timestamp') {
            $columns[$subfield]['type'] = 'datestamp';
            $columns[$subfield]['mysql_type'] = 'timestamp';
            $columns[$subfield]['pgsql_type'] = 'timestamp';
            $columns[$subfield]['sqlite_type'] = 'varchar';
            $columns[$subfield]['sqlsrv_type'] = 'rowversion';
          }
          if ($item['datetime_type'] == 'time') {
            $columns[$subfield]['type'] = 'time';
            $columns[$subfield]['mysql_type'] = 'time';
            $columns[$subfield]['pgsql_type'] = 'time';
            $columns[$subfield]['sqlite_type'] = 'varchar';
            $columns[$subfield]['sqlsrv_type'] = 'time';
          }
          if ($item['datetime_type'] == 'year') {
            $columns[$subfield]['type'] = 'year';
            $columns[$subfield]['mysql_type'] = 'year';
            $columns[$subfield]['pgsql_type'] = 'varchar';
            $columns[$subfield]['sqlite_type'] = 'smallint';
            $columns[$subfield]['sqlsrv_type'] = 'smallint';
          }
          if ($item['datetime_type'] == 'month') {
            $columns[$subfield]['type'] = 'varchar';
            $columns[$subfield]['length'] = 8;
          }
          if ($item['datetime_type'] == 'week') {
            $columns[$subfield]['type'] = 'varchar';
            $columns[$subfield]['length'] = 9;
          }
          break;

        case 'blob':
          $columns[$subfield]['type'] = 'blob';
          $columns[$subfield]['size'] = $item['size'] ?? 'big';
          break;

        case 'json':
          $columns[$subfield]['type'] = 'json';
          $columns[$subfield]['pgsql_type'] = 'json';
          $columns[$subfield]['mysql_type'] = 'json';
          break;
      }
    }

    return ['columns' => $columns];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];

    // Prevent early t() calls by using the TranslatableMarkup.
    foreach ($field_definition->getSetting('columns') as $item) {
      $data_type = $item['type'] ?? 'string';
      switch ($data_type) {
        case 'url':
        case 'text':
        case 'json':
        case 'email':
        case 'varchar':
        case 'telephone':
        case 'date':
          $data_type = 'string';
          break;

        case 'numeric':
          $data_type = 'float';
          break;

        case 'blob':
          $data_type = 'binary';
          break;

        case 'entity_reference':
        case 'file':
          $data_type = 'integer';
          break;
      }
      if ($data_type == 'datetime_iso8601') {
        switch ($item['datetime_type']) {
          case 'time':
          case 'date':
            $data_type = 'string';
            break;

          case 'datetime':
            $data_type = 'datetime_iso8601';
            break;

          case 'timestamp':
            $data_type = 'timestamp';
            break;
        }
        if ($data_type == 'date') {
          switch ($item['datetime_type']) {
            case 'timespan':
              $data_type = 'timespan';
              break;

            case 'year':
              $data_type = 'integer';
              break;
          }
        }
      }

      $properties[$item['name']] = DataDefinition::create($data_type)
        ->setLabel(new TranslatableMarkup('%name value', ['%name' => $item['name']]))
        ->setRequired(FALSE);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $settings = $this->getSettings();
    if (empty($columns = $settings["columns"])) {
      return FALSE;
    }
    foreach ($columns as $subfield => $item) {
      if ($item['type'] == 'boolean') {
        // Booleans can be 1 or 0.
        if ($this->{$subfield} == 1) {
          return FALSE;
        }
      }
      elseif ($this->{$subfield} !== NULL && $this->{$subfield} !== '') {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Element validate callback for subfield allowed values.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   generic form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form for the form this element belongs to.
   *
   * @see \Drupal\Core\Render\Element\FormElement::processPattern()
   */
  public static function validateAllowedValues(array $element, FormStateInterface $form_state): void {
    $values = static::extractAllowedValues($element['#value']);

    // Check if keys are valid for the field type.
    foreach ($values as $key => $value) {
      switch ($element['#storage_type']) {

        case 'string':
          // @see \Drupal\options\Plugin\Field\FieldType\ListStringItem::validateAllowedValue()
          if (mb_strlen($key) > $element['#storage_max_length']) {
            $error_message = t(
              'Allowed values list: each key must be a string at most @maxlength characters long.',
              ['@maxlength' => $element['#storage_max_length']]
            );
            $form_state->setError($element, $error_message);
          }
          break;

        case 'integer':
          // @see \Drupal\options\Plugin\Field\FieldType\ListIntegerItem::validateAllowedValue()
          if (!preg_match('/^-?\d+$/', $key)) {
            $form_state->setError($element, ('Allowed values list: keys must be integers.'));
          }
          break;

        case 'float':
        case 'numeric':
          // @see \Drupal\options\Plugin\Field\FieldType\ListFloatItem::validateAllowedValue()
          if (!is_numeric($key)) {
            $form_state->setError($element, ('Allowed values list: each key must be a valid integer or decimal.'));
          }
          break;

      }
    }

    $form_state->setValueForElement($element, $values);
  }

  /**
   * Extracts the allowed values array from the allowed_values element.
   *
   * @param string $string
   *   The raw string to extract values from.
   *
   * @return array
   *   The array of extracted key/value pairs.
   *
   * @see \Drupal\options\Plugin\Field\FieldType\ListTextItem::extractAllowedValues()
   */
  protected static function extractAllowedValues(string $string): array {

    $values = [];

    $list = explode("\n", $string);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    foreach ($list as $text) {
      // Check for an explicit key.
      if (preg_match('/(.*)\|(.*)/', $text, $matches)) {
        // Trim key and value to avoid unwanted spaces issues.
        $key = trim($matches[1]);
        $value = trim($matches[2]);
      }
      else {
        $key = $value = $text;
      }
      $values[$key] = $value;
    }

    return $values;
  }

  /**
   * Generates a string representation of an array of 'allowed values'.
   *
   * This string format is suitable for edition in a textarea.
   *
   * @param array $values
   *   An array of values, where array keys are values and array values are
   *   labels.
   *
   * @return string
   *   The string representation of the $values array:
   *    - Values are separated by a carriage return.
   *    - Each value is in the format "value|label" or "value".
   */
  protected function allowedValuesString(array $values): string {
    $lines = [];
    foreach ($values as $key => $value) {
      $lines[] = "$key|$value";
    }
    return implode("\n", $lines);
  }

  /**
   * Returns available subfield storage types.
   */
  public static function subfieldTypes(): array {
    return [
      'boolean' => t('Boolean'),
      'string' => t('Text'),
      'text' => t('Text (long)'),
      'integer' => t('Integer'),
      'float' => t('Float'),
      'numeric' => t('Decimal'),
      'datetime_iso8601' => t('Date ISO'),
      'date' => t('Date'),
      'email' => t('Email'),
      'telephone' => t('Telephone'),
      // We only allow external links. So this should be URL from the user side.
      'uri' => t('Url'),
      'json' => t('Json'),
      'blob' => t('Blob'),
      'file' => t('File'),
      'entity_reference' => t('Entity reference'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldSettingsToConfigData(array $settings): array {
    foreach ($settings['field_settings'] as $subfield => $item) {
      if (empty($item['allowed_values'])) {
        continue;
      }
      $structured_values = [];
      foreach ($item['allowed_values'] as $value => $label) {
        $structured_values[] = [
          'value' => $value,
          'label' => $label,
        ];
      }
      $settings['field_settings'][$subfield]['allowed_values'] = $structured_values;
    }
    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function fieldSettingsFromConfigData(array $settings): array {
    if (empty($settings['field_settings'])) {
      return $settings;
    }
    foreach ($settings['field_settings'] as $field => $subfield) {
      if (empty($subfield['allowed_values'])) {
        continue;
      }
      $structured_values = [];
      foreach ($subfield['allowed_values'] as $item) {
        $structured_values[$item['value']] = $item['label'];
      }
      $settings["field_settings"][$field]["allowed_values"] = $structured_values;
    }
    return $settings;
  }

  /**
   * Checks if list option is allowed for a given sub-field type.
   */
  public static function isListAllowed(string $subfield_type): bool {
    $list_types = array_keys(self::subfieldTypes());
    $notAllowed = [
      'boolean', 'text', 'datetime_iso8601', 'date', 'json', 'blob', 'file', 'entity_reference',
    ];
    return in_array($subfield_type, $list_types) && !in_array($subfield_type, $notAllowed);
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $settings = $field_definition->getSettings();

    $data = [];
    foreach ($settings['columns'] as $subfield => $item) {

      // If allowed values are limited pick one of them from field settings.
      if ($settings[$subfield]['list']) {
        $data[$subfield] = array_rand($settings['field_settings'][$subfield]['allowed_values']);
        continue;
      }

      switch ($item['type']) {

        // @see \Drupal\Core\Field\Plugin\Field\FieldType\BooleanItem::generateSampleValue()
        case 'boolean':
          $data[$subfield] = (bool) mt_rand(0, 1);
          break;

        // @see \Drupal\datetime\Plugin\Field\FieldType\DateTimeItem::generateSampleValue()
        case 'datetime_iso8601':
          $date_type = $item['datetime_type'];
          $timestamp = \Drupal::time()->getRequestTime() - mt_rand(0, 86400 * 365);
          $storage_format = $date_type == 'date' ? self::DATETIME_DATE_STORAGE_FORMAT : self::DATETIME_DATETIME_STORAGE_FORMAT;
          $data[$subfield] = gmdate($storage_format, $timestamp);
          break;

        // @see \Drupal\Core\Field\Plugin\Field\FieldType\StringItem::generateSampleValue()
        case 'uuid':
        case 'string':
          $data[$subfield] = (new Random())->word(mt_rand(1, $item['max_length']));
          break;

        // @see \Drupal\text\Plugin\Field\FieldType\TextItemBase::generateSampleValue()
        case 'text':
          $data[$subfield] = (new Random())->paragraphs(5);
          break;

        // @see \Drupal\Core\Field\Plugin\Field\FieldType\IntegerItem::generateSampleValue()
        case 'integer':
          $min = is_numeric($settings['field_settings'][$subfield]['min']) ? $settings['field_settings'][$subfield]['min'] : -1000;
          $max = is_numeric($settings['field_settings'][$subfield]['max']) ? $settings['field_settings'][$subfield]['max'] : 1000;
          $data[$subfield] = mt_rand($min, $max);
          break;

        // @see \Drupal\Core\Field\Plugin\Field\FieldType\FloatItem::generateSampleValue()
        case 'float':
          $settings = $field_definition->getSettings();
          $precision = rand(10, 32);
          $scale = rand(1, 5);
          $max = is_numeric($settings['field_settings'][$subfield]['min']) ? $settings['field_settings'][$subfield]['min'] : pow(10, ($precision - $scale)) - 1;
          $min = is_numeric($settings['field_settings'][$subfield]['max']) ? $settings['field_settings'][$subfield]['max'] : -pow(10, ($precision - $scale)) + 1;
          $random_decimal = $min + mt_rand() / mt_getrandmax() * ($max - $min);
          $data[$subfield] = floor($random_decimal * pow(10, $scale)) / pow(10, $scale);
          break;

        // @see \Drupal\Core\Field\Plugin\Field\FieldType\DecimalItem::generateSampleValue()
        case 'numeric':
          $precision = $item['precision'] ?: 10;
          $scale = $item['scale'] ?: 2;
          $min = is_numeric($settings['field_settings'][$subfield]['min']) ? $settings['field_settings'][$subfield]['min'] : -pow(10, ($precision - $scale)) + 1;
          $max = is_numeric($settings['field_settings'][$subfield]['max']) ? $settings['field_settings'][$subfield]['max'] : pow(10, ($precision - $scale)) - 1;

          $set_decimal_digits = function ($decimal) {
            $digits = 0;
            while ($decimal - round($decimal)) {
              $decimal *= 10;
              $digits++;
            }
            return $digits;
          };

          $decimal_digits = $set_decimal_digits($max);
          $decimal_digits = max($set_decimal_digits($min), $decimal_digits);
          $scale = rand($decimal_digits, $scale);
          $random_decimal = $min + mt_rand() / mt_getrandmax() * ($max - $min);
          $data[$subfield] = floor($random_decimal * pow(10, $scale)) / pow(10, $scale);
          break;

        // @see \Drupal\Core\Field\Plugin\Field\FieldType\EmailItem::generateSampleValue()
        case 'email':
          $data[$subfield] = strtolower((new Random())->name()) . '@example.com';
          break;

        // @see \Drupal\telephone\Plugin\Field\FieldType\TelephoneItem::generateSampleValue()
        case 'telephone':
          $data[$subfield] = mt_rand(pow(10, 8), pow(10, 9) - 1);
          break;

        // @see \Drupal\link\Plugin\Field\FieldType\LinkItem::generateSampleValue()
        case 'uri':
          $random = new Random();
          $tlds = ['com', 'net', 'gov', 'org', 'edu', 'biz', 'info'];
          $domain_length = mt_rand(7, 15);
          $protocol = mt_rand(0, 1) ? 'https' : 'http';
          $www = mt_rand(0, 1) ? 'www' : '';
          $domain = $random->word($domain_length);
          $tld = $tlds[mt_rand(0, (count($tlds) - 1))];
          $data[$subfield] = "$protocol://$www.$domain.$tld";
          break;

      }
    }

    return $data;
  }

  /**
   * Creates date object for a given subfield using storage timezone.
   */
  public function createDate(string $subfield): ?DateTimePlus {
    $date = NULL;
    if ($value = $this->{$subfield}) {
      $storage = $this->getSetting('columns')[$subfield];
      $dateType = $storage['datetime_type'];
      $is_date_only = $dateType == 'date';
      if ($dateType == 'timestamp') {
        if ($storage["type"] == 'date') {
          $value = strtotime($value);
        }
        $date = DrupalDateTime::createFromTimestamp($value, static::DATETIME_STORAGE_TIMEZONE);
      }
      else {
        $format = $is_date_only ? static::DATETIME_DATE_STORAGE_FORMAT : static::DATETIME_DATETIME_STORAGE_FORMAT;
        if ($storage["type"] == 'date') {
          switch ($dateType) {
            case 'datetime':
              $format = 'Y-m-d H:i:s';
              break;

            case 'time':
              $format = 'H:i:s';
              break;

            case 'year':
              $format = 'Y';
              break;
          }
        }
        $date = DrupalDateTime::createFromFormat($format, $this->{$subfield}, static::DATETIME_STORAGE_TIMEZONE);
      }
      if ($is_date_only) {
        $date->setDefaultDateTime();
      }
    }
    return $date;
  }

  /**
   * Check for duplicate names on our columns settings.
   */
  public function machineNameExists($value, array $form, FormStateInterface $form_state) {
    $count = 0;
    foreach ($form_state->getValue(['settings', 'storage']) as $item) {
      if ($item['name'] == $value) {
        $count++;
      }
    }
    return $count > 1;
  }

  /**
   * Callback for both ajax-enabled buttons.
   *
   * Selects and returns the fieldset with the names in it.
   */
  public function actionCallback(array &$form, FormStateInterface $form_state) {
    return $form['settings']['storage'];
  }

  /**
   * Submit handler for the "add-one-more" button.
   *
   * Increments the max counter and causes a rebuild.
   */
  public static function addSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('add', TRUE);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for the "remove one" button.
   *
   * Decrements the max counter and causes a form rebuild.
   */
  public static function removeSubmit(array &$form, FormStateInterface $form_state) {
    $form_state->set('remove', $form_state->getTriggeringElement()['#delta']);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    return NULL;
  }

}
