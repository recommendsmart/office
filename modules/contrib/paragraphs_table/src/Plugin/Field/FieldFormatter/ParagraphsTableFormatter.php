<?php

namespace Drupal\paragraphs_table\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\Markup;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\FieldConfigInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Plugin implementation of the 'paragraphs_table_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "paragraphs_table_formatter",
 *   module = "paragraphs_table",
 *   label = @Translation("Paragraphs table"),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class ParagraphsTableFormatter extends EntityReferenceFormatterBase {

  /**
   * Field permission.
   *
   * @var string
   */
  protected $typePermission = 'public';

  /**
   * Field Permissions Values.
   *
   * @var array
   */
  protected $customPermissions = [
    'create' => FALSE,
    'view' => TRUE,
    'view own' => TRUE,
    'edit' => FALSE,
    'edit own' => FALSE,
  ];
  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The selection plugin manager.
   *
   * @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface
   */
  protected $selectionManager;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $requestStack;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selection_manager
   *   The selection plugin manager.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityDisplayRepositoryInterface $entity_display_repository, RendererInterface $renderer, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, CurrentPathStack $current_path, SelectionPluginManagerInterface $selection_manager, Request $request, EntityFieldManagerInterface $entity_field_manager, EntityTypeBundleInfoInterface $entity_type_bundle_info) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->entityDisplayRepository = $entity_display_repository;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->moduleHandler = $module_handler;
    $this->currentUser = $current_user;
    $this->currentPath = $current_path;
    $this->selectionManager = $selection_manager;
    $this->requestStack = $request;
    $this->entityFieldManager = $entity_field_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_display.repository'),
      $container->get('renderer'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('path.current'),
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('entity_field.manager'),
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    // Implement default settings.
    return [
      'view_mode' => 'default',
      'vertical' => FALSE,
      'caption' => '',
      'mode' => '',
      'chart_type' => '',
      'chart_width' => 900,
      'chart_height' => 300,
      'import' => '',
      'empty_cell_value' => FALSE,
      'empty' => FALSE,
      'ajax' => FALSE,
      'custom_class' => '',
      'hide_line_operations' => FALSE,
      'form_format_table' => TRUE,
      'footer_text' => '',
      'sum_fields' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $settingForm = [
      'view_mode' => [
        '#type' => 'select',
        '#options' => $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type')),
        '#title' => $this->t('View mode'),
        '#default_value' => $this->getSetting('view_mode'),
        '#required' => TRUE,
      ],
      'caption' => [
        '#title' => $this->t('Caption'),
        '#description' => $this->t('Caption of table') .
        $this->t('Variable available {{ paragraph_name }}, {{ paragraph_type }}, {{ entity_type }}, {{ entity_field }}, {{ entity_id }}'),
        '#type' => 'textfield',
        '#maxlength' => 512,
        '#default_value' => $this->getSettings()['caption'],
      ],
      'vertical' => [
        '#title' => $this->t('Table vertical'),
        '#description' => $this->t('If checked, table data will show in vertical mode'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSettings()['vertical'],
      ],
      'mode' => [
        '#title' => $this->t('Table Mode'),
        '#description' => $this->t('Select the table extension.'),
        '#type' => 'select',
        '#default_value' => $this->getSettings()['mode'],
        '#options' => $this->getConfigurableViewModes(),
        '#empty_option' => $this->t('Default'),
        '#states' => [
          'visible' => [
            'input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][vertical]"]' => ['checked' => FALSE],
          ],
        ],
      ],
      'chart_type' => [
        '#title' => $this->t('Chart type'),
        '#description' => '<a href="https://developers-dot-devsite-v2-prod.appspot.com/chart/interactive/docs/gallery" target="_blank">' . $this->t('Google charts') . '</a>',
        '#type' => 'select',
        '#default_value' => $this->getSettings()['chart_type'],
        '#options' => $this->googleChartsOption(),
        '#empty_option' => $this->t('Default'),
        '#states' => [
          'visible' => [
            'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][mode]"]' => ['value' => 'googleCharts'],
          ],
        ],
      ],
      'chart_width' => [
        '#title' => $this->t('Chart width'),
        '#type' => 'number',
        '#default_value' => $this->getSettings()['chart_width'],
        '#states' => [
          'visible' => [
            'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][mode]"]' => ['value' => 'googleCharts'],
          ],
        ],
      ],
      'chart_height' => [
        '#title' => $this->t('Chart height'),
        '#type' => 'number',
        '#default_value' => $this->getSettings()['chart_height'],
        '#states' => [
          'visible' => [
            'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][mode]"]' => ['value' => 'googleCharts'],
          ],
        ],
      ],
      'empty_cell_value' => [
        '#title' => $this->t('Fill Blank Cells in table'),
        '#description' => $this->t('The string which should be displayed in empty cells. Defaults to an empty string.'),
        '#type' => 'textfield',
        '#default_value' => $this->getSettings()['empty_cell_value'],
      ],
      'empty' => [
        '#title' => $this->t('Hide empty columns'),
        '#description' => $this->t('If enabled, hide empty paragraphs table columns'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSettings()['empty'],
        '#states' => [
          'visible' => [
            'input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][vertical]"]' => ['checked' => FALSE],
          ],
        ],
      ],
      'ajax' => [
        '#title' => $this->t('Load table with ajax'),
        '#description' => $this->t('You can use for big data, it will call ajax to load table data'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSettings()['ajax'],
        '#states' => [
          'visible' => [
            'input[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][vertical]"]' => ['checked' => FALSE],
          ],
        ],
      ],
      'custom_class' => [
        '#title' => $this->t('Set table class'),
        '#type' => 'textfield',
        '#default_value' => $this->getSettings()['custom_class'],
      ],
      'hide_line_operations' => [
        '#title' => $this->t('Hide line operations'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSettings()['hide_line_operations'],
      ],
      'form_format_table' => [
        '#title' => $this->t('Format table in add / edit form'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSettings()['form_format_table'],
      ],
      'footer_text' => [
        '#title' => $this->t('Custom text at the footer of each paragraph'),
        '#description' => $this->t('Variable available {{ paragraph_name }}, {{ paragraph_type }}, {{ entity_type }}, {{ entity_field }}, {{ entity_id }}'),
        '#type' => 'textarea',
        '#default_value' => $this->getSettings()['footer_text'],
      ],
    ];
    $options_number_field = [];
    $target_type = $this->getFieldSetting('target_type');
    $field_name = $this->fieldDefinition->getName();
    $entity_type = $this->fieldDefinition->getTargetEntityTypeId();
    $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    foreach ($bundle_info as $bundle => $label) {
      $fieldDefinitions = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
      if (!empty($fieldDefinitions[$field_name])) {
        break;
      }
    }
    if (!empty($fieldDefinitions[$field_name])) {
      $target_bundles = $fieldDefinitions[$field_name]->getSettings()["handler_settings"]["target_bundles"];
      $target_bundle = current($target_bundles);
      $paragraphs_entity = $this->entityTypeManager->getStorage($target_type)
        ->create(['type' => $target_bundle]);
      $field_definitions = $paragraphs_entity->getFieldDefinitions();
      $typSupport = ['integer', 'number', 'number_integer', 'bigint',
        'float', 'list_float', 'decimal',
      ];
      foreach ($field_definitions as $fieldName => $field_definition) {
        if ($field_definition instanceof FieldConfig) {
          $type = $field_definition->getType();
          if (in_array($type, $typSupport)) {
            $options_number_field[$fieldName] = $field_definition->getLabel();
          }
        }
      }
    }
    if (!empty($options_number_field)) {
      $settingForm['sum_fields'] = [
        '#title' => $this->t('Sum fields'),
        '#description' => $this->t('use for number field'),
        '#type' => 'select',
        '#multiple' => TRUE,
        '#options' => $options_number_field,
        '#default_value' => $this->getSettings()['sum_fields'],
        '#states' => [
          'visible' => [
            'select[name$="[mode]"]' => ['value' => 'bootstrapTable'],
          ],
        ],
      ];
    }
    if ($this->moduleHandler->moduleExists('quick_data')) {
      $settingForm['import'] = [
        '#title' => $this->t('Import link title'),
        '#description' => $this->t("Leave it blank if you don't want to import csv data"),
        '#type' => 'textfield',
        '#default_value' => $this->getSettings()['import'],
      ];
    }
    return $settingForm + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    // Implement settings summary.
    $view_modes = $this->entityDisplayRepository->getViewModeOptions($this->getFieldSetting('target_type'));
    $view_mode = $this->getSetting('view_mode');
    $summary[] = $this->t('Rendered as @view_mode', ['@view_mode' => !empty($view_modes[$view_mode]) ? $view_modes[$view_mode] : $view_mode]);

    if (!empty($this->getSetting('caption'))) {
      $summary[] = $this->t('Caption: @caption', ['@caption' => strip_tags($this->getSetting('caption'))]);
    }
    if (!empty($this->getSetting('vertical'))) {
      $summary[] = $this->t('Table mode vertical');
    }
    if (!empty($this->getSetting('mode'))) {
      $summary[] = $this->t('Mode: @mode', ['@mode' => $this->getSetting('mode')]);
    }
    if (!empty($this->getSetting('chart_type'))) {
      $summary[] = $this->t('Chart type: @type', ['@type' => $this->getSetting('chart_type')]);
    }
    if (!empty($this->getSetting('import'))) {
      $summary[] = $this->t('Label import csv: @import', ['@import' => $this->getSetting('import')]);
    }
    if (!empty($this->getSetting('empty_cell_value'))) {
      $summary[] = $this->t('Replace empty cells with: @replace', ['@replace' => $this->getSetting('empty_cell_value')]);
    }
    if (!empty($this->getSetting('empty'))) {
      $summary[] = $this->t('Hide empty columns');
    }
    if (!empty($this->getSetting('ajax'))) {
      $summary[] = $this->t('Load ajax');
    }
    if (!empty($this->getSetting('custom_class'))) {
      $summary[] = $this->t('Custom class: @class', ['@class' => $this->getSetting('custom_class')]);
    }
    if (!empty($this->getSetting('hide_line_operations'))) {
      $summary[] = $this->t('Hide line operations.');
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $setting = $this->getSettings();
    $targetType = $this->getFieldSetting('target_type');
    $field_definition = $items->getFieldDefinition();
    // Support field permission.
    if ($this->moduleHandler->moduleExists('field_permissions')) {
      $this->setCustomPermissions($field_definition, $items);
      if (!$this->customPermissions['view']) {
        return [];
      }
    }
    $entity = $items->getEntity();
    $entityId = $entity->id();
    // In edit Mode if entity have multiple paragraphs table
    // we will have 1st paragraphs field.
    $field_name_current = $field_definition->getName();
    $request = $this->requestStack->request;
    if ($field_name_current != $trigger_name = $request->get('_triggering_add_paragraphs')) {
      if ($entity->hasField($trigger_name) && empty($request->get('edit_' . $trigger_name))) {
        $items = $entity->get($trigger_name);
        $field_definition = $items->getFieldDefinition();
      }
    }
    $output = [];
    $handler = $field_definition->getSetting("handler_settings");
    if (empty($handler["target_bundles"])) {
      return $output;
    }
    $entities = $this->getEntitiesToView($items, $langcode);
    $fieldName = $field_definition->getName();
    if (!empty($setting['hide_line_operations'])) {
      $hasPermission = FALSE;
    }
    else {
      $hasPermission = $this->checkPermissionOperation($entity, $fieldName);
      if (!$hasPermission && !empty($this->customPermissions['view'])) {
        $hasPermission = TRUE;
      }
    }

    // Context for header or footer.
    $selectionHandler = $this->selectionManager->getSelectionHandler($field_definition);
    $bundles = $selectionHandler->entityTypeBundleInfo->getBundleInfo($targetType);

    $type = key($handler["target_bundles"]);
    $entityType = $entity->bundle();
    if (method_exists($entity, 'getType')) {
      $entityType = $entity->getType();
    }
    $context = [
      'paragraph_name' => $bundles[$type]['label'],
      'paragraph_type' => $type,
      'entity_type' => $entityType,
      'entity_field' => $field_name_current,
      'entity_id' => $entityId,
    ];

    foreach ($handler["target_bundles"] as $targetBundle) {
      $table = $table_header = $fields = [];
      /** @var \Drupal\paragraphs\ParagraphInterface $paragraphs_entity */
      $paragraphs_entity = $this->entityTypeManager->getStorage($targetType)
        ->create(['type' => $targetBundle]);
      $field_definitions = $paragraphs_entity->getFieldDefinitions();
      $view_mode = $setting['view_mode'];
      $viewDisplay = $this->entityDisplayRepository->getViewDisplay($targetType, $targetBundle, $view_mode);
      $components = $viewDisplay->getComponents();
      uasort($components, 'Drupal\Component\Utility\SortArray::sortByWeightElement');

      foreach ($components as $field_name => $component) {
        if (!empty($field_definitions[$field_name]) && $field_definitions[$field_name] instanceof FieldConfigInterface) {
          $fields[$field_name] = $field_definitions[$field_name];
          $table_header[$field_name] = $this->t("%name",
            ["%name" => $field_definitions[$field_name]->getLabel()]
          );
        }
      }
      if ($hasPermission) {
        $table_header['operation'] = '';
      }
      $table_rows = $notEmptyColumn = [];
      if (in_array($setting['mode'], ['datatables', 'bootstrapTable']) || empty($setting['mode'])) {
        if (empty($setting["ajax"]) && !empty($entities)) {
          $table_rows = $this->getPreparedRenderedEntities($targetType, $targetBundle, $entities, $fields, $notEmptyColumn, $view_mode, $hasPermission);
          // Remove empty columns.
          if (!empty($setting["empty"]) && !empty($notEmptyColumn)) {
            foreach ($table_header as $field_name => $label_column) {
              if ($field_name == 'operation') {
                continue;
              }
              if (empty($notEmptyColumn[$field_name])) {
                unset($table_header[$field_name]);
                foreach ($table_rows as &$row) {
                  if (isset($row['data'][$field_name])) {
                    unset($row['data'][$field_name]);
                  }
                }
              }
            }
          }
        }
        if (!empty($setting["vertical"])) {
          $table = $this->getTableVertical($table_header, $table_rows, $entities);
        }
        else {
          $table = $this->getTable($table_header, $table_rows, $entities);
          if (empty($table_rows) && $setting['empty']) {
            unset($table["#header"]);
          }
        }
      }
      $table_id = implode('-', [$targetType, $targetBundle]);
      $table['#attributes']['id'] = $table_id;
      $table['#attributes']['class'][] = 'paragraphs-table';
      if (!empty($setting['caption'])) {
        $table['#caption'] = [
          '#type' => 'inline_template',
          '#template' => $setting['caption'],
          '#context' => $context,
        ];
        $table['#attributes']['class'][] = 'caption-top';
      }
      if (!empty($setting['custom_class'])) {
        $table['#attributes']['class'][] = $setting['custom_class'];
      }
      switch ($setting['mode']) {
        case 'datatables':
          $datatable_options = $this->datatablesOption($table_header, $components, $langcode);

          if (!empty($setting["ajax"]) && $entityId) {
            $url = Url::fromRoute('paragraphs_item.json', [
              'field_name' => $fieldName,
              'host_type' => $field_definition->getTargetEntityTypeId(),
              'host_id' => $entityId,
            ]);
            $datatable_options["ajax"] = $url->toString();
          }

          if (!empty($setting['footer_text'])) {
            $table['#footer'] = [
              [
                'data' => [
                  [
                    'data' => [
                      '#type' => 'inline_template',
                      '#template' => $setting['footer_text'],
                      '#context' => $context,
                    ],
                    'colspan' => count($table_header),
                  ],
                ],
              ],
            ];
          }

          $table['#attributes']['width'] = '100%';
          $table['#attached']['library'][] = 'paragraphs_table/datatables_core';
          $table['#attached']['drupalSettings']['datatables']['#' . $table_id] = $datatable_options;
          $table['#attached']['library'][] = 'paragraphs_table/datatables';
          break;

        case 'bootstrapTable':
          $bootstrapTable_options = $this->bootstrapTableOption($table_header, $components, $langcode);
          foreach ($bootstrapTable_options as $dataTable => $valueData) {
            $table['#attributes']["data-$dataTable"] = $valueData;
          }
          if (!empty($setting["ajax"]) && $entityId) {
            $url = Url::fromRoute('paragraphs_item.json', [
              'field_name' => $fieldName,
              'host_type' => $field_definition->getTargetEntityTypeId(),
              'host_id' => $entityId,
            ], ['query' => ['type' => 'rows']]);
            $table['#attributes']["data-url"] = $url->toString();
          }
          foreach ($table_header as $field_name => $field_label) {
            $table_header[$field_name] = [
              'data' => $field_label,
              'data-field' => $field_name,
              'data-sortable' => "true",
            ];
            if (!empty($setting['sum_fields']) && in_array($field_name, $setting['sum_fields'])) {
              $table_header[$field_name]['data-footer-formatter'] = 'sumFormatter';
            }
          }
          $table['#header'] = $table_header;
          if (!empty($setting['footer_text'])) {
            $table['#footer'] = [
              [
                'class' => ['footer'],
                'data' => [
                  [
                    'data' => [
                      '#type' => 'inline_template',
                      '#template' => $setting['footer_text'],
                      '#context' => $context,
                    ],
                    'colspan' => count($table_header),
                  ],
                ],
              ],
            ];
            $table['#attributes']["data-show-footer"] = 'true';
          }
          if (!empty($setting['sum_fields'])) {
            $table['#attributes']["data-show-footer"] = 'true';
          }
          $table['#attached']['library'][] = 'paragraphs_table/bootstrapTable';
          break;

        case 'googleCharts':
          $table = $this->settingGoogleChart($setting, $table_id);
          if (empty($setting["ajax"])) {
            $data = $this->getData($targetType, $targetBundle, $entities, $fields, $notEmptyColumn, $view_mode);
            if (isset($table_header["operation"])) {
              unset($table_header["operation"]);
            }
            if (empty($options['notHeader'])) {
              $data = array_merge([array_values($table_header)], $data);
            }
            $table['#attached']['drupalSettings']['googleCharts']['#' . $table_id]['data'] = $data;
          }
          else {
            $url = Url::fromRoute('paragraphs_item.jsonData', [
              'field_name' => $fieldName,
              'host_type' => $field_definition->getTargetEntityTypeId(),
              'host_id' => $entityId,
            ]);
            $table['#attached']['drupalSettings']['googleCharts']['#' . $table_id]['options']['url'] = $url->toString();
          }
          break;

        default:
          if (!empty($setting["ajax"])) {
            $url = Url::fromRoute('paragraphs_item.ajax', [
              'field_name' => $fieldName,
              'host_type' => $field_definition->getTargetEntityTypeId(),
              'host_id' => $entityId,
            ]);
            $table['#attached']['drupalSettings']['paragraphsTable']['#' . $table_id] = [
              'id' => $table_id,
              'view_mode' => $view_mode,
              'url' => $url->toString(),
            ];
            $table['#attached']['library'][] = 'paragraphs_table/paragraphsTable';
          }
          break;

      }
      // Alter table results.
      $this->moduleHandler
        ->alter('paragraphs_table_formatter', $table, $table_id);
      $output[] = $table;
    }
    $addButton = NULL;
    $userRoles = $this->currentUser->getRoles();
    $cardinality = $field_definition->getFieldStorageDefinition()->get('cardinality');
    if ($entityId &&
      (($hasPermission && $this->customPermissions['create']) || in_array('administrator', $userRoles)) &&
      ($cardinality == -1 || $cardinality > $items->count())) {
      $destination = $this->currentPath->getPath();
      $dialog_width = 800;
      $addButton = [
        '#type' => 'container',
        'add-button' => [
          '#type' => 'link',
          '#title' => Markup::create('<i class="bi bi-plus" aria-hidden="true"></i> ' . $this->t('Add')),
          '#url' => Url::fromRoute('paragraphs_item.add_page', $params = [
            'paragraph_type' => $targetBundle,
            'entity_type' => $field_definition->getTargetEntityTypeId(),
            'entity_field' => $fieldName,
            'entity_id' => $entityId,
          ], [
            'query' => [
              'destination' => $destination,
              'formatTable' => $setting['form_format_table'],
            ],
          ]),
          '#attributes' => [
            'class' => [
              'btn', 'btn-success',
              'use-ajax',
            ],
            'data-dialog-type' => 'modal',
            'data-dialog-options' => Json::encode(['width' => $dialog_width]),
          ],
        ],
        '#attributes' => [
          'class' => [$targetBundle . '--' . $fieldName],
          'id' => HTML::getId(implode('-', $params)),
        ],
      ];
      if (!empty($setting["import"]) &&
        $this->moduleHandler->moduleExists('quick_data')) {
        $addButton['import-button'] = [
          '#type' => 'link',
          '#title' => Markup::create(
            implode(' ', [
              '<i class="bi bi-filetype-csv"></i>',
              $this->t('@import', ['@import' => $setting["import"]]),
            ])
          ),
          '#url' => Url::fromRoute('quick_data.paragraph.import', $params = [
            'type' => $targetType,
            'bundle' => $field_definition->getTargetEntityTypeId(),
            'field' => $fieldName,
            'entity_id' => $entityId,
          ], [
            'query' => [
              'destination' => $destination,
              'formatTable' => $setting['form_format_table'],
            ],
          ]),
          '#attributes' => [
            'class' => [
              'btn', 'btn-primary',
            ],
          ],
        ];
      }
      $arrInfo = [$entity, $field_definition->getName()];
      $form_state = (new FormState())
        ->set('langcode', $langcode)
        ->disableRedirect()
        ->addBuildInfo('args', $arrInfo);
      $edit_btn = 'edit_' . $fieldName;
      if (!empty($redirect = $form_state->getRedirect())) {
        return new RedirectResponse($redirect->toString());
      }
      $triggering_input = $form_state->getUserInput();
      if (!empty($triggering_input)) {
        $triggering_element = $form_state->getTriggeringElement();
        $temp = !empty($triggering_element["#name"]) ? explode("_", $triggering_element["#name"]) : [];
        $trigger_mode = end($temp);
        if (!empty($triggering_input[$edit_btn]) ||
          in_array($trigger_mode, ['remove', 'duplicate'])) {
          $table['#attributes']['class'][] = 'hidden';
          $table['#access'] = FALSE;
        }
      }
    }
    if ($addButton) {
      $output[] = $addButton;
    }
    return $output;
  }

  /**
   * Setting for table googleChart.
   *
   * @param array $setting
   *   Settings.
   * @param string $table_id
   *   Table id.
   *
   * @return array
   *   The table setting.
   */
  protected function settingGoogleChart(array $setting = [], $table_id = '') {
    $options = $this->googleChartsOption($setting['chart_type']);
    $options['url'] = FALSE;
    if (!empty($setting['caption'])) {
      $options['title'] = $setting['caption'];
    }
    if (is_numeric($setting['chart_width'])) {
      $setting['chart_width'] .= 'px';
    }
    if (is_numeric($setting['chart_height'])) {
      $setting['chart_height'] .= 'px';
    }
    if (empty($setting['chart_width'])) {
      $setting['chart_width'] = '100%';
    }
    $table = [
      '#theme' => 'paragraphs_table_chart',
      '#settings' => $setting,
      '#id_field_name' => $table_id,
    ];
    $table['#attached']['drupalSettings']['googleCharts']['#' . $table_id] = [
      'id' => $table_id,
      'type' => !empty($setting['chart_type']) ? $setting['chart_type'] : 'BarChart',
      'options' => $options,
    ];
    $table['#attached']['library'][] = 'paragraphs_table/googleCharts';
    return $table;
  }

  /**
   * Generate the output appropriate for one field item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   One field item.
   *
   * @return string
   *   The textual output generated.
   */
  protected function viewValue(FieldItemInterface $item) {
    // The text value has no text format assigned to it, so the user input
    // should equal the output, including newlines.
    return nl2br(Html::escape($item->value));
  }

  /**
   * Get the entities which will make up the table.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   The field items.
   *
   * @return array
   *   An array of loaded entities.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getEntities(FieldItemListInterface $items) {
    $entity_type = $this->fieldDefinition->getFieldStorageDefinition()
      ->getSetting('target_type');
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entities = [];
    foreach ($items as $item) {
      $entity_id = $item->getValue()['target_id'];
      if ($entity_id) {
        $entity = $entity_storage->load($entity_id);
        if ($entity && $entity->access('view')) {
          $entities[] = $entity;
        }
      }
    }
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function getTable($table_columns, $table_rows, $entities) {
    $table = [
      '#theme' => 'table',
      '#rows' => $table_rows,
      '#header' => $table_columns,
    ];
    $this->cacheMetadata($entities, $table);
    return $table;
  }

  /**
   * {@inheritdoc}
   */
  public function getData($type, $bundle, $entities, $fields, &$notEmptyColumn, $view_mode = 'default') {
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    // When a display renderer doesn't exist, fall back to the default.
    $renderer = $storage->load(implode('.', [$type, $bundle, $view_mode]));
    $setting = $this->getSettings();
    $data = [];
    foreach ($entities as $delta => $entitie) {
      $table_entity = $renderer->build($entitie);
      foreach ($fields as $field_name => $field) {
        $table_entity[$field_name]['#label_display'] = 'hidden';
        $value = trim(strip_tags($this->renderer->render($table_entity[$field_name])));
        if (in_array($field->getType(), [
          'integer',
          'list_integer',
          'number_integer',
        ])) {
          $value = (int) $value;
        }
        if (in_array($field->getType(), ['boolean'])) {
          $list_value = $table_entity[$field_name]["#items"]->getValue();
          $value = (int) $list_value[0]['value'];
        }
        if (in_array($field->getType(), [
          'decimal',
          'list_decimal',
          'number_decimal',
          'float',
          'list_float',
          'number_float',
        ])) {
          $value = (float) $value;
        }
        if (!empty($value)) {
          $notEmptyColumn[$field_name] = TRUE;
        }
        elseif (!empty($setting["empty_cell_value"])) {
          $value = $setting["empty_cell_value"];
        }

        $data[$delta][] = $value;
      }
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function getTableVertical($table_columns, $table_rows, $entities) {
    $rows = [];
    foreach ($table_rows as $delta => $row) {
      if (count($table_rows) > 1) {
        $operation = !empty($row['data']['operation']) ? $row['data']['operation'] : '';
        $rows[] = [
          'data' => [['data' => $operation, 'colspan' => 2]],
          'class' => ['paragraphs-item', 'action'],
          'data-quickedit-entity-id' => $row['data-quickedit-entity-id'],
          'data-id' => $row['data-id'],
          'data-type' => $row['data-type'],
        ];
      }
      foreach ($row['data'] as $field_name => $value) {
        if (!empty($table_columns[$field_name])) {
          $rows[] = [
            'data' => [
              'label' => [
                'data' => $this->t("%name", ['%name' => $table_columns[$field_name]]),
                'class' => ['field__label', $field_name],
              ],
              'item' => [
                'data' => $value,
                'class' => ['field__item', $field_name],
              ],
            ],
            'class' => ['field-paragraphs-item'],
            'id' => 'item-' . $delta,
          ];
        }
      }
    }
    $table = [
      '#theme' => 'table',
      '#rows' => $rows,
    ];
    $this->cacheMetadata($entities, $table);

    return $table;
  }

  /**
   * Cache.
   */
  protected function cacheMetadata($entities, &$table) {
    $cache_metadata = new CacheableMetadata();
    foreach ($entities as $entity) {
      $cache_metadata->addCacheableDependency($entity);
      $cache_metadata->addCacheableDependency($entity->access('view', NULL, TRUE));
    }
    $cache_metadata->applyTo($table);
  }

  /**
   * Prepare all the given entities for rendering with applicable fields.
   */
  protected function getPreparedRenderedEntities($type, $bundle, $entities, $fields, &$notEmptyColumn, $view_mode = 'default', $show_operation = FALSE) {
    $storage = $this->entityTypeManager->getStorage('entity_view_display');
    // When a display renderer doesn't exist, fall back to the default.
    $renderer = $storage->load(implode('.', [$type, $bundle, $view_mode]));
    if (empty($renderer)) {
      $renderer = $storage->load(implode('.', [$type, $bundle, 'default']));
    }
    $setting = $this->getSettings();
    $rows = [];
    foreach ($entities as $delta => $entity) {
      $entity_bundle = $entity->bundle();
      if ($bundle != $entity_bundle || empty($renderer)) {
        continue;
      }
      $table_entity = $renderer->build($entity);
      $paragraphs_id = $entity->get('id')->value;
      foreach ($fields as $field_name => $field) {
        $table_entity[$field_name]['#label_display'] = 'hidden';
        $value = $this->renderer->render($table_entity[$field_name]);
        if (!empty($value)) {
          $notEmptyColumn[$field_name] = TRUE;
        }
        elseif (!empty($setting["empty_cell_value"])) {
          $value = $setting["empty_cell_value"];
        }
        $rows[$delta]['data'][$field_name] = $value;
        $field_type = $field->getType();

        // Add data order for sort.
        if ($setting['mode'] == 'datatables' &&
          in_array($field_type, [
            'timestamp',
            'datetime',
            'daterange',
            'datestamp',
            'smartdate',
            'number_decimal',
            'decimal',
            'number_float',
            'list_float',
          ]) &&
          !empty($table_entity[$field_name]["#items"])) {

          $list_value = $table_entity[$field_name]["#items"]->getValue();
          $value_order = $list_value[0]['value'];
          if (!is_numeric($value_order)) {
            $value_order = strtotime($value_order);
          }
          $rows[$delta]['data'][$field_name] = [
            'data' => $value,
            'data-order' => $value_order,
          ];
        }
      }
      if ($show_operation && $parent = $entity->getParentEntity()) {
        $entityType = $parent->getEntityTypeId();
        if ($entityType == 'paragraph') {
          $entityType = 'paragraphs_item';
        }
        $destination = implode('/', [
          $entityType,
          $parent->id(),
        ]);
        $operation = $this->paragraphsTableLinksAction($paragraphs_id, $destination);
        $rows[$delta]['data']['operation'] = $operation;
      }

      $rows[$delta]['data-quickedit-entity-id'] = "$type/$paragraphs_id";
      $rows[$delta]["data-id"] = $paragraphs_id;
      $rows[$delta]["data-type"] = $type;
    }
    return $rows;
  }

  /**
   * Support Datatable https://datatables.net/ https://bootstrap-table.com/.
   */
  public function getConfigurableViewModes() {
    return [
      'datatables' => $this->t('Datatables'),
      'bootstrapTable' => $this->t('Bootstrap Table'),
      'googleCharts' => $this->t('Google Charts'),
    ];
  }

  /**
   * Links action.
   */
  private function paragraphsTableLinksAction($paragraphsId = FALSE, $destination = '') {
    $route_params = [
      'paragraph' => $paragraphsId,
    ];
    if (!empty($destination)) {
      $route_params['destination'] = $destination;
    }
    $operation = [
      '#type' => 'dropbutton',
      '#links' => [
        'view' => [
          'title' => $this->t('View'),
          'url' => Url::fromRoute('entity.paragraphs_item.canonical', $route_params),
        ],
        'edit' => [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute('entity.paragraphs_item.edit_form', $route_params),
        ],
        'duplicate' => [
          'title' => $this->t('Duplicate'),
          'url' => Url::fromRoute('entity.paragraphs_item.clone_form', $route_params),
        ],
        'delete' => [
          'title' => $this->t('Remove'),
          'url' => Url::fromRoute('entity.paragraphs_item.delete_form', $route_params),
        ],
      ],
    ];
    $userRoles = $this->currentUser->getRoles();
    if (!in_array('administrator', $userRoles)) {
      if (!$this->customPermissions['edit']) {
        unset($operation['#links']['edit']);
        unset($operation['#links']['duplicate']);
      }
      if (!$this->customPermissions['create']) {
        unset($operation['#links']['delete']);
      }
      if (!$this->customPermissions['view']) {
        unset($operation['#links']['view']);
      }
      if (!$this->customPermissions['delete']) {
        unset($operation['#links']['delete']);
      }
    }

    // Alter row operation.
    $this->moduleHandler
      ->alter('paragraphs_table_operations', $operation, $paragraphsId);
    return $this->renderer->render($operation);
  }

  /**
   * Support Bootstrap Table.
   */
  public function bootstrapTableOption($header, $components, $langcode = 'en') {
    $data_option = [
      'toggle' => 'table',
      'search' => "true",
      'show-search-clear-button' => "true",
      'show-refresh' => "true",
      'show-toggle' => "true",
      'show-fullscreen' => "true",
      'show-columns' => "true",
      'show-columns-toggle-all' => "true",
      'mobile-responsive' => "true",
      'show-print' => "true",
      'show-copy-rows' => "true",
      'show-export' => "true",
      'sortable' => "true",
      'click-to-select' => "true",
      'minimum-count-columns' => "2",
      'show-pagination-switch' => "true",
      'pagination' => "true",
      'page-list' => "[10, 25, 50, 100, all]",
      'show-footer' => "false",
    ];

    $languages = [
      'af' => 'af-ZA',
      'am' => 'am-ET',
      'ar' => 'ar-AE',
      'az' => 'az-Latn-AZ',
      'be' => 'be-BY',
      'bg' => 'bg-BG',
      'ca' => 'ca-ES',
      'cs' => 'cs-CZ',
      'cy' => 'cy-GB',
      'da' => 'da-DK',
      'de' => 'de-DE',
      'el' => 'el-GR',
      'eo' => 'eo-EO',
      'es' => 'es-ES',
      'et' => 'et-EE',
      'eu' => 'eu-EU',
      'fa' => 'fa-IR',
      'fi' => 'fi-fi',
      'fr' => 'fr-FR',
      'ga' => 'ga-IE',
      'gl' => 'gl-ES',
      'gu' => 'gu-IN',
      'he' => 'he-IL',
      'hi' => 'hi-IN',
      'hr' => 'hr-HR',
      'hu' => 'hu-HU',
      'hy' => 'hy-AM',
      'id' => 'id-ID',
      'is' => 'is-IS',
      'it' => 'it-CH',
      'ja' => 'ja-JP',
      'ka' => 'ka-GE',
      'kk' => 'kk-KZ',
      'km' => 'km-KH',
      'ko' => 'ko-KR',
      'ky' => 'ky-KG',
      'lo' => 'lo-LA',
      'lt' => 'lt-LT',
      'lv' => 'lv-LV',
      'mk' => 'mk-MK',
      'ml' => 'ml-IN',
      'mn' => 'mn-MN',
      'ne' => 'ne-NP',
      'nl' => 'nl-NL',
      'nb' => 'nb-NO',
      'nn' => 'nn-NO',
      'pa' => 'pa-IN',
      'pl' => 'pl-PL',
      'pt' => 'pt-PT',
      'ro' => 'ro-RO',
      'ru' => 'ru-RU',
      'si' => 'si-LK',
      'sk' => 'sk-SK',
      'sl' => 'sl-SI',
      'sq' => 'sq-AL',
      'sr' => 'sr-Latn-RS',
      'sv' => 'sv-SE',
      'sw' => 'sw-KE',
      'ta' => 'ta-IN',
      'te' => 'te-IN',
      'th' => 'th-TH',
      'tr' => 'tr-TR',
      'uk' => 'uk-UA',
      'ur' => 'ur-PK',
      'vi' => 'vn-VN',
      'fil' => 'fi-FI',
      'zh-hans' => 'zh-CN',
      'zh-hant' => 'zh-TW',
    ];
    if (!empty($languages[$langcode])) {
      $data_option['locale'] = $languages[$langcode];
    }
    return $data_option;
  }

  /**
   * Datatable Options.
   */
  public function datatablesOption($header, $components, $langcode = 'en') {
    $datatable_options = [
      'bExpandable' => TRUE,
      'bInfo' => TRUE,
      'dom' => 'Bfrtip',
      "scrollX" => TRUE,
      'bStateSave' => FALSE,
      "ordering" => TRUE,
      'searching' => TRUE,
      'bMultiFilter' => FALSE,
      'bMultiFilter_position' => "header",
    ];
    foreach ($header as $field_name => $field_label) {
      $datatable_options['aoColumnHeaders'][] = $field_label;
      $column_options = [
        'name' => $field_name,
        'data' => $field_name,
        'orderable' => TRUE,
        'type' => 'html',
      ];

      // Attempt to autodetect the type of field
      // in order to handle sorting correctly.
      if (!empty($components[$field_name]) && in_array($components[$field_name]['type'], [
        'number_decimal',
        'number_integer',
        'number_float',
        'list_float',
        'list_integer',
      ])) {
        $column_options['type'] = 'html-num';
      }
      if (!empty($components[$field_name]) && in_array($components[$field_name]['type'], [
        'datetime',
        'date',
        'datestamp',
      ])) {
        $column_options['type'] = 'date-fr';
      }
      $datatable_options['columns'][] = $column_options;
    }
    $langNonSupport = ['ast', 'bn', 'bo', 'bs', 'dz', 'fo', 'fy', 'gd', 'gsw',
      'ht', 'jv', 'kn', 'mg', 'mr', 'ms', 'my', 'oc', 'sco', 'se', 'tyv',
      'ug', 'xx',
    ];
    $explode = explode('-', $langcode);
    $langcode = current($explode);
    if (!empty($langcode) && !isset($langNonSupport[$langcode])) {
      $cdn_lang = '//cdn.datatables.net/plug-ins/';
      $version = '1.12.1';
      $language_url = $cdn_lang . $version . '/i18n/' . $langcode . '.json';
      $datatable_options['language']['url'] = $language_url;
    }

    return $datatable_options;
  }

  /**
   * Support google chart.
   */
  private function googleChartsOption($option = FALSE) {
    $options = [
      'BarChart' => [
        'title' => $this->t('Bar'),
        'option' => [
          'bar' => ['groupWidth' => "95%"],
          'legend' => ['position' => "none"],
        ],
      ],
      'BubbleChart' => [
        'title' => $this->t('Bubble'),
        'option' => [
          'bubble' => ['textStyle' => ['fontSize' => 11]],
        ],
      ],
      'LineChart' => [
        'title' => $this->t('Line'),
        'option' => [
          'legend' => ['position' => "bottom"],
          'curveType' => 'function',
        ],
      ],
      'ColumnChart' => [
        'title' => $this->t('Column'),
        'option' => [
          'bar' => ['groupWidth' => "95%"],
          'legend' => ['position' => "none"],
        ],
      ],
      'ComboChart' => [
        'title' => $this->t('Combo'),
        'option' => [
          'seriesType' => 'bars',
        ],
      ],
      'PieChart' => [
        'title' => $this->t('Pie'),
        'option' => [
          'is3D' => TRUE,
        ],
      ],
      'ScatterChart' => [
        'title' => $this->t('Scatter'),
        'option' => [
          'legend' => ['position' => "none"],
        ],
      ],
      'SteppedAreaChart' => [
        'title' => $this->t('Stepped Area'),
        'option' => [
          'isStacked' => TRUE,
        ],
      ],
      'AreaChart' => [
        'title' => $this->t('Area'),
        'option' => [
          'legend' => ['position' => "top", 'maxLines' => 3],
          'isStacked' => 'relative',
        ],
      ],
      'Histogram' => [
        'title' => $this->t('Histogram'),
        'option' => [
          'legend' => ['position' => "top", 'maxLines' => 3],
          'interpolateNulls' => FALSE,
        ],
      ],
      'CandlestickChart' => [
        'title' => $this->t('Candlestick'),
        'option' => [
          'notHeader' => TRUE,
          'legend' => 'none',
          'bar' => ['groupWidth' => '100%'],
        ],
      ],
    ];
    if ($option) {
      return $options[$option]['option'];
    }
    $titleOptions = [];
    foreach ($options as $type => $option) {
      $titleOptions[$type] = $option['title'];
    }
    return $titleOptions;
  }

  /**
   * Check permission Operation.
   */
  public static function checkPermissionOperation($entity, $fieldName) {
    $user = \Drupal::currentUser();
    $permissions = [
      'bypass node access',
      'administer nodes',
      'administer paragraphs_item fields',
    ];
    foreach ($permissions as $permission) {
      if ($user->hasPermission($permission)) {
        return TRUE;
      }
    }
    $moduleHandler = \Drupal::service('module_handler');
    // Check paragraphs permission.
    if ($moduleHandler->moduleExists('paragraphs_type_permissions')) {
      if ($user->hasPermission('bypass paragraphs type content access')) {
        return TRUE;
      }
      if (method_exists($entity, 'bundle')) {
        $bundle = $entity->bundle();
        $permissions = [
          'create paragraph content ' . $bundle,
          'update paragraph content ' . $bundle,
          'delete paragraph content ' . $bundle,
        ];
        foreach ($permissions as $permission) {
          if ($user->hasPermission($permission)) {
            return TRUE;
          }
        }
      }
    }
    // Check field permission.
    if ($moduleHandler->moduleExists('field_permissions')) {
      if ($user->hasPermission('access private fields')) {
        return TRUE;
      }
      $permissions = [
        'create ' . $fieldName,
        'edit ' . $fieldName,
        'edit own ' . $fieldName,
      ];
      foreach ($permissions as $permission) {
        if ($user->hasPermission($permission)) {
          return TRUE;
        }
      }
    }
    $entityType = $entity->getEntityTypeId();
    if ($entityType != 'user') {
      $bundle = $entity->bundle();
      $permissions = [
        "create $bundle content",
        "update $bundle content",
        "delete $bundle content",
      ];
      foreach ($permissions as $permission) {
        if ($user->hasPermission($permission)) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

  /**
   * Set Custom Permissions.
   */
  private function setCustomPermissions(FieldDefinitionInterface $field_definition, FieldItemListInterface $items) {
    // Check paragraphs permission.
    if ($this->moduleHandler->moduleExists('paragraphs_type_permissions')) {
      if ($this->currentUser->hasPermission('bypass paragraphs type content access')) {
        $this->customPermissions = [
          'create' => TRUE,
          'view' => TRUE,
          'edit' => TRUE,
          'delete' => TRUE,
        ];
        return TRUE;
      }

      $target_bundles = $field_definition->getSettings()["handler_settings"]["target_bundles"];
      if (!empty($target_bundles)) {
        $target_bundle = current($target_bundles);
        $this->customPermissions = [
          'create' => $this->currentUser->hasPermission('create paragraph content ' . $target_bundle),
          'view' => $this->currentUser->hasPermission('view paragraph content ' . $target_bundle),
          'edit' => $this->currentUser->hasPermission('update paragraph content ' . $target_bundle),
          'delete' => $this->currentUser->hasPermission('delete paragraph content ' . $target_bundle),
        ];
        return TRUE;
      }
    }

    $field_permissions_type = $field_definition->getFieldStorageDefinition()
      ->getThirdPartySettings('field_permissions');
    $this->typePermission = !empty($field_permissions_type['permission_type']) ? $field_permissions_type['permission_type'] : FALSE;
    if ($this->typePermission == 'custom') {
      $fieldName = $field_definition->getName();
      $this->customPermissions = [
        'create' => $this->currentUser->hasPermission('create ' . $fieldName),
        'view' => $this->currentUser->hasPermission('view ' . $fieldName),
        'view own' => $this->currentUser->hasPermission('view own ' . $fieldName),
        'edit' => $this->currentUser->hasPermission('edit ' . $fieldName),
        'edit own' => $this->currentUser->hasPermission('edit own ' . $fieldName),
        'delete' => $this->currentUser->hasPermission('create ' . $fieldName),
      ];
      return TRUE;
    }
    return FALSE;
  }

}
