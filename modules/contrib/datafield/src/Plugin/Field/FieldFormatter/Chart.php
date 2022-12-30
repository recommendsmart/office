<?php

namespace Drupal\datafield\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Plugin implementations for 'chart' formatter.
 *
 * @FieldFormatter(
 *   id = "data_field_chart",
 *   label = @Translation("Charts"),
 *   field_types = {"data_field"}
 * )
 */
class Chart extends Base {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'mode' => 'googleCharts',
      'chart_type' => 'LineChart',
      'chart_width' => 900,
      'chart_height' => 300,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    $element['mode'] = [
      '#type' => 'select',
      '#options' => [
        'googleCharts' => $this->t('Google chart'),
        'highChart' => $this->t('High chart'),
      ],
      '#default_value' => $this->getSetting('mode'),
    ];
    $element['chart_type'] = [
      '#title' => $this->t('Chart type'),
      '#description' => '<a href="https://developers-dot-devsite-v2-prod.appspot.com/chart/interactive/docs/gallery" target="_blank">' . $this->t('Google charts') . '</a>',
      '#type' => 'select',
      '#default_value' => $this->getSetting('chart_type'),
      '#options' => $this->googleChartsOption(),
      '#empty_option' => $this->t('Default'),
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][mode]"]' => ['value' => 'googleCharts'],
        ],
      ],
    ];
    $element['chart_width'] = [
      '#title' => $this->t('Chart width'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('chart_width'),
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][mode]"]' => ['value' => 'googleCharts'],
        ],
      ],
    ];
    $element['chart_height'] = [
      '#title' => $this->t('Chart height'),
      '#type' => 'number',
      '#default_value' => $this->getSetting('chart_height'),
      '#states' => [
        'visible' => [
          'select[name="fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings][mode]"]' => ['value' => 'googleCharts'],
        ],
      ],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary['mode'] = "Mode: " . $this->getSetting('mode');
    $summary['chart_type'] = "Type: " . $this->getSetting('chart_type');
    return $summary;
  }

  /**
   * {@inheritDoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $field_name = $items->getName();
    $setting = $this->getSettings();
    $drupalSettings = [
      $this->getSetting('mode') => ['#' . $field_name => $setting],
    ];

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
    $elements = [
      '#theme' => 'data_field_chart',
      '#settings' => $setting,
      '#id_field_name' => $field_name,
      '#langcode' => $langcode,
      '#attributes' => [
        'data-json-field' => $field_name,
        'class' => [$this->getSetting('mode')],
      ],
    ];

    if (!empty($this->getSetting('custom_class'))) {
      $elements['#attributes']['class'][] = $this->getSetting('custom_class');
    }

    if (!empty($items)) {
      switch ($this->getSetting('mode')) {
        case 'googleCharts':
          $elements['#attached'] = [
            'library' => ['datafield/googleCharts'],
            'drupalSettings' => $drupalSettings,
          ];
          break;

        case 'highChart':
          $elements['#attached'] = [
            'library' => ['datafield/highcharts'],
            'drupalSettings' => $drupalSettings,
          ];
          break;

        default:
          $elements = [];
      }
    }
    $data = [];
    $storages = $this->getFieldSetting('columns');
    $field_settings = $this->getFieldSetting('field_settings');
    $subfields = array_keys($storages);
    if (!empty($settings = $setting['formatter_settings'])) {
      uasort($settings, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = array_keys($settings);
    }
    $header = [];
    foreach ($items as $delta => $item) {
      foreach ($subfields as $subfield) {
        if (!$delta) {
          $header[] = $field_settings[$subfield]["label"] ?? $storages[$subfield]['name'];
        }
        $value = $item->{$subfield};
        if (!is_array($value) && !is_object($value)) {
          $value = trim(strip_tags($value));
        }
        else {
          $value = trim(strip_tags(\Drupal::service('renderer')
            ->render($value)));
        }
        if (is_numeric($value)) {
          $data[$delta][] = (float) $value;
        }
        else {
          $data[$delta][] = $value;
        }
      }
    }
    $elements['#attached']['drupalSettings'][$this->getSetting('mode')]['#' . $field_name] += [
      'id' => $field_name,
      'type' => $this->getSetting('chart_type'),
      'options' => $options,
      'data' => [...[$header], ...$data],
    ];
    if (!empty($this->getSetting('form_format_table'))) {
      $entity = $items->getEntity();
      $entityId = $entity->id();
      $field_definition = $items->getFieldDefinition();
      $hasPermission = $this->checkPermissionOperation($entity, $field_name);
      if ($entityId && $hasPermission) {
        $dialog_width = '80%';
        $elements[] = [
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

    return $elements;
  }

  /**
   * {@inheritDoc}
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

}
