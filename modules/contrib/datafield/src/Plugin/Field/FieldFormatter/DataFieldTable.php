<?php

namespace Drupal\datafield\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Plugin implementations for 'table' formatter.
 *
 * @FieldFormatter(
 *   id = "data_field_table_formatter",
 *   label = @Translation("Table"),
 *   field_types = {"data_field"}
 * )
 */
class DataFieldTable extends Base {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $default = [
      'direction' => 'horizontal',
      'mode' => 'table',
      'number_column' => FALSE,
      'number_column_label' => '№',
      'caption' => '',
      'footer_text' => '',
    ];
    return $default + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();
    $element = parent::settingsForm($form, $form_state);
    $element['number_column'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable row number column'),
      '#default_value' => $settings['number_column'],
      '#attributes' => ['class' => [Html::getId('js-data_field-number-column')]],
    ];
    $element['number_column_label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Number column label'),
      '#size' => 30,
      '#default_value' => $settings['number_column_label'],
      '#states' => [
        'visible' => ['.js-data-field-number-column' => ['checked' => TRUE]],
      ],
    ];
    $element['mode'] = [
      '#type' => 'select',
      '#options' => [
        'table' => $this->t('Table'),
        'datatables' => $this->t('Datatables'),
        'bootstrap-table' => $this->t('Bootstrap table'),
      ],
      '#default_value' => $this->getSetting('mode'),
    ];
    $element['direction'] = [
      '#title' => $this->t('Direction'),
      '#type' => 'select',
      '#options' => [
        'horizontal' => $this->t('Horizontal'),
        'vertical' => $this->t('Vertical'),
      ],
      '#default_value' => $this->getSetting('direction'),
    ];
    $element['caption'] = [
      '#title' => $this->t('Caption'),
      '#description' => $this->t('Caption of table.') .
      $this->t('Variable available {{ entity_type }}, {{ entity_bundle }}, {{ entity_field }}, {{ entity_id }}'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('caption'),
    ];
    $subfields = array_keys($field_settings['columns']);
    if (!empty($setting = $settings['formatter_settings'])) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = array_keys($setting);
    }
    foreach ($subfields as $subfield) {
      $item = $field_settings['columns'][$subfield];
      $title = $field_settings["field_settings"][$subfield]["label"] ?? $item['name'];
      $element['formatter_settings'][$subfield]['column_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Column label: %title', ['%title' => $title]),
        '#size' => 30,
        '#attributes' => ['placeholder' => $this->t('Column label')],
        '#default_value' => $setting[$subfield]['column_label'] ?? $title,
      ];
      if (in_array($field_settings['columns'][$subfield]['type'], [
        'numeric',
        'integer',
        'float',
      ])) {
        $element['formatter_settings'][$subfield]['sum_column'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Sum column'),
          '#default_value' => $setting[$subfield]['sum_column'] ?? 0,
        ];
      }
    }

    $element['footer_text'] = [
      '#title' => $this->t('Custom text at the footer'),
      '#description' => $this->t('Variable available {{ entity_type }}, {{ entity_bundle }}, {{ entity_field }}, {{ entity_id }}'),
      '#type' => 'textarea',
      '#default_value' => $this->getSetting('footer_text'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $field_settings = $this->getFieldSettings();
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Enable row number column: @number_column', ['@number_column' => $settings['number_column'] ? $this->t('yes') : $this->t('no')]);
    if ($settings['number_column']) {
      $summary[] = $this->t('Number column label: @number_column_label', ['@number_column_label' => $settings['number_column_label']]);
    }

    $subfields = array_keys($field_settings['columns']);
    foreach ($subfields as $subfield) {
      if (!empty($settings['formatter_settings'][$subfield])) {
        $summary[] = ucfirst($subfield) . ' ' .
          $this->t('column label: @column_label',
            ['@column_label' => $settings['formatter_settings'][$subfield]['column_label'] ?? '']
          );
      }
    }
    $summary['mode'] = "Mode: " . $this->getSetting('mode');
    $summary['direction'] = "Direction: " . $this->getSetting('direction');

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $field_settings = $this->getFieldSettings();
    $components = $field_settings["columns"];
    $settings = $this->getSettings();
    $entity = $items->getEntity();
    $entityId = $entity->id();
    $field_definition = $items->getFieldDefinition();
    $field_name = $items->getName();
    $table = ['#type' => 'table'];

    // No other way to pass context to the theme.
    // @see datafield_theme_suggestions_table_alter()
    $id = HTML::getId('data_field');
    $table['#attributes']['data-field--field-name'] = $field_name;
    $table['#attributes']['class'][] = $id . '-table';
    if (!empty($settings['custom_class'])) {
      $table['#attributes']['class'][] = $settings['custom_class'];
    }

    $subfields = array_keys($field_settings['columns']);

    $context = [
      'entity_bundle' => $entity->bundle(),
      'entity_type' => $field_definition->getTargetEntityTypeId(),
      'entity_field' => $field_name,
      'entity_id' => $entityId,
    ];

    if (!empty($setting = $settings['formatter_settings'])) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = array_keys($setting);
    }
    $header = [];
    if (!empty($settings['number_column'])) {
      $header = ['index_col' => $settings['number_column_label']];
    }
    $hasPermission = $this->checkPermissionOperation($entity, $field_name);
    foreach ($subfields as $subfield) {
      if (empty($settings['number_column_label'])) {
        $header = [];
        break;
      }
      $header[$subfield] = $setting[$subfield]['column_label'] ?? $field_settings["field_settings"][$subfield]["label"];
    }
    if (!empty($settings['caption'])) {
      $table['#caption'] = [
        '#type' => 'inline_template',
        '#template' => $settings['caption'],
        '#context' => $context,
      ];
      $table['#attributes']['class'][] = 'caption-top';
    }
    if (!empty($header)) {
      if (!empty($this->getSetting("line_operations") && $hasPermission)) {
        $header['operation'] = '';
      }
      if ($this->getSetting("direction") != 'vertical') {
        $table['#header'] = $header;
      }
      switch ($settings["mode"]) {
        case 'datatables':
          $datatable_options = $this->datatablesOption($header, $components, $langcode);
          $table['#attributes']['width'] = '100%';
          $table['#attributes']['css'][] = 'data-table';
          $table['#attached']['library'][] = 'datafield/datafield_datatables';
          $table['#attached']['drupalSettings']['datatables'][$field_name] = $datatable_options;
          break;

        case 'bootstrap-table':
          $bootstrapTable_options = $this->bootstrapTableOption($header, $components, $langcode);
          foreach ($bootstrapTable_options as $dataTable => $valueData) {
            $table['#attributes']["data-$dataTable"] = $valueData;
          }
          $table['#attributes']["data-cookie-id-table"] = $field_name;
          $table_header = [];
          foreach ($header as $field_name => $field_label) {
            $table_header[$field_name] = [
              'data' => $field_label,
              'data-field' => $field_name,
              'data-sortable' => "true",
            ];
          }

          if (!empty($this->getSetting("line_operations") && $hasPermission)) {
            $table_header['operation'] = '';
          }

          if ($this->getSetting("direction") == 'vertical') {
            $table_header = ['name', 'value'];
            if ($settings['number_column']) {
              $table_header = [
                $settings["number_column_label"],
                $this->t('Name'),
                $this->t('Value'),
              ];
            }
          }
          $table['#header'] = $table_header;
          if (!empty($settings['footer_text'])) {
            $table['#footer'] = [
              [
                'class' => ['footer'],
                'data' => [
                  [
                    'data' => [
                      '#type' => 'inline_template',
                      '#template' => $settings['footer_text'],
                      '#context' => $context,
                    ],
                    'colspan' => count($table_header),
                  ],
                ],
              ],
            ];
            $table['#attributes']["data-show-footer"] = 'true';
          }
          $table['#attached']['library'][] = 'datafield/datafield_bootstraptable';
          break;
      }
    }
    $verticalIndex = 1;
    $sum_column = [];
    foreach ($items as $delta => $item) {
      if ($this->getSetting("direction") == 'vertical') {
        foreach ($subfields as $subfield) {
          if (!empty($setting[$subfield]["sum_column"]) && is_numeric($item->{$subfield})) {
            if (empty($sum_column[$subfield])) {
              $sum_column[$subfield] = 0;
            }
            $sum_column[$subfield] += $item->{$subfield};
          }
          $row = [];
          if ($settings['number_column']) {
            $row[]['#markup'] = $verticalIndex++;
          }
          $label = '';
          if (!empty($settings["formatter_settings"][$subfield]["show_label"])) {
            $label = $settings["formatter_settings"][$subfield]["column_label"] ?? $field_settings['field_settings'][$subfield]["label"];
          }
          $row[]['#markup'] = $label;
          $row[] = [
            '#theme' => 'data_field_subfield',
            '#settings' => $settings,
            '#subfield' => $item->{$subfield},
            '#index' => $subfield,
            '#field_name' => $field_name,
          ];
          $table[] = $row;
        }
      }
      else {
        $row = [];
        if ($settings['number_column']) {
          $row[]['#markup'] = $delta + 1;
        }
        foreach ($subfields as $subfield) {
          if (!empty($setting[$subfield]["sum_column"]) && is_numeric($item->{$subfield})) {
            if (empty($sum_column[$subfield])) {
              $sum_column[$subfield] = 0;
            }
            $sum_column[$subfield] += $item->{$subfield};
          }
          if (!empty($settings["formatter_settings"][$subfield]) && $settings["formatter_settings"][$subfield]['hidden']) {
            $row[]['#markup'] = '';
          }
          else {
            $label = '';
            if (!empty($settings["formatter_settings"][$subfield]["show_label"])) {
              $label = $settings["formatter_settings"][$subfield]["column_label"] ?? $field_settings['field_settings'][$subfield]["label"];
            }
            $row[] = [
              '#theme' => 'data_field_subfield',
              '#settings' => $settings,
              '#subfield' => $item->{$subfield},
              '#index' => $subfield,
              '#field_name' => $field_name,
              '#label' => $label,
            ];
          }
        }
        if (!empty($this->getSetting("line_operations") && $hasPermission)) {
          $route_params = [
            'entity_type' => $field_definition->getTargetEntityTypeId(),
            'field_name' => $field_name,
            'entity' => $entityId,
            'delta' => $delta,
          ];
          $operation = [
            '#type' => 'dropbutton',
            '#links' => [
              'edit' => [
                'title' => $this->t('Edit'),
                'url' => Url::fromRoute('datafield.edit_form', $route_params),
              ],
              'duplicate' => [
                'title' => $this->t('Duplicate'),
                'url' => Url::fromRoute('datafield.clone_form', $route_params),
              ],
              'delete' => [
                'title' => $this->t('Remove'),
                'url' => Url::fromRoute('datafield.delete_form', $route_params),
              ],
            ],
          ];
          $row[] = $operation;
        }
        $table[$delta] = $row;
      }
    }

    if (!empty($header) && !empty($sum_column)) {
      foreach (array_keys($header) as $colName) {
        $footer[$colName] = $sum_column[$colName] ?? '';
      }
      if (!empty($footer)) {
        $table['#footer'] = [$footer];
      }
    }
    $element[0] = $table;
    if (!empty($this->getSetting('form_format_table'))) {
      if ($entityId && $hasPermission) {
        $dialog_width = '80%';
        $element[] = [
          '#type' => 'container',
          'add-button' => [
            '#type' => 'link',
            '#title' => Markup::create('<i class="bi bi-plus" aria-hidden="true"></i> ' . $this->t('Add')),
            '#url' => Url::fromRoute('datafield.add_form', $params = [
              'entity_type' => $field_definition->getTargetEntityTypeId(),
              'field_name' => $field_name,
              'entity' => $entityId,
            ]),
            '#attributes' => [
              'class' => [
                'btn',
                'btn-success',
                'use-ajax',
              ],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode(['width' => $dialog_width]),
            ],
          ],
          'edit-button' => [
            '#type' => 'link',
            '#title' => Markup::create('<i class="bi bi-pencil-square"></i> ' . $this->t('Edit')),
            '#url' => Url::fromRoute('datafield.edit_all_form', $params = [
              'entity_type' => $field_definition->getTargetEntityTypeId(),
              'field_name' => $field_name,
              'entity' => $entityId,
            ]),
            '#attributes' => [
              'class' => [
                'btn',
                'btn-info',
                'use-ajax',
              ],
              'data-dialog-type' => 'modal',
              'data-dialog-options' => Json::encode(['width' => $dialog_width]),
            ],
          ],
        ];
      }
    }
    return $element;
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
      'cookie' => "true",
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

      // Attempt to autodetect the type of field in order to handle sorting.
      if (!empty($components[$field_name]) &&
      in_array($components[$field_name]['type'], [
        'number_decimal', 'number_integer', 'number_float',
        'list_float', 'list_integer',
      ])) {
        $column_options['type'] = 'html-num';
      }
      if (!empty($components[$field_name]) &&
        in_array($components[$field_name]['type'],
          ['datetime', 'date', 'datestamp'])) {
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
      $langConvert = [
        'it' => 'it-IT',
        'nl' => 'nl-NL',
        'no' => 'no-NB',
        'no' => 'no-NO',
        'pt' => 'pt-PT',
        'sv' => 'sv-SE',
        'es' => 'es-ES',
        'fr' => 'fr-FR',
        'az' => 'az-AZ',
        'bs' => 'bs-BA',
        'en' => 'en-GB',
      ];
      if (!empty($langConvert[$langcode])) {
        $langcode = $langConvert[$langcode];
      }
      $cdn_lang = '//cdn.datatables.net/plug-ins/';
      $version = '1.13.1';
      $language_url = $cdn_lang . $version . '/i18n/' . $langcode . '.json';
      $datatable_options['language']['url'] = $language_url;
    }

    return $datatable_options;
  }

}
