<?php

namespace Drupal\datafield\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Plugin implementations for 'details' formatter.
 *
 * @FieldFormatter(
 *   id = "data_field_details",
 *   label = @Translation("Details"),
 *   field_types = {"data_field"}
 * )
 */
class Details extends Base {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'open' => TRUE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $settings = $this->getSettings();
    $element = parent::settingsForm($form, $form_state);
    $element['open'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Open'),
      '#default_value' => $settings['open'],
    ];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSettings();
    $summary = parent::settingsSummary();
    if (!empty($settings['open'])) {
      $summary[] = $this->t('Open: @open', ['@open' => $settings['open'] ? $this->t('yes') : $this->t('no')]);
    }
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];
    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();

    $subfields = array_keys($field_settings["columns"]);
    if (!empty($setting = $settings['formatter_settings'])) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = array_keys($setting);
    }
    $attributes = [
      Html::getId('data_field--field-name') => $items->getName(),
      'class' => ['data-field-details'],
    ];

    if (!empty($this->getSetting('custom_class'))) {
      $attributes['class'][] = $this->getSetting('custom_class');
    }

    foreach ($items as $delta => $item) {

      $values = [];
      $labels = [];
      foreach ($subfields as $subfield) {
        $values[$subfield] = $item->{$subfield};
        $labels[$subfield] = $field_settings['field_settings'][$subfield]["label"] ?? '';
      }
      $firstValues = array_shift($values);
      $firstKey = current($subfields);
      if (!empty($labels[$firstKey])) {
        $firstValues = [
          '#theme' => 'data_field_subfield',
          '#subfield' => $firstValues,
          '#label' => $labels[$firstKey],
          '#field_name' => $firstKey,
          '#index' => $firstKey,
        ];
      }

      $element[$delta] = [
        '#title' => $firstValues,
        '#type' => 'details',
        '#open' => $settings['open'],
        '#attributes' => $attributes,
      ];
      foreach ($values as $subfield => $value) {
        if (!empty($labels[$subfield])) {
          $value = [
            '#theme' => 'data_field_subfield',
            '#subfield' => $value,
            '#label' => $labels[$subfield],
            '#field_name' => $subfield,
            '#index' => $subfield,
          ];
        }
        $element[$delta]['#value'][$subfield] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => \Drupal::service('renderer')->render($value),
          '#attributes' => [
            'class' => [$subfield],
          ],
        ];
      }
    }

    if (!empty($this->getSetting('form_format_table'))) {
      $entity = $items->getEntity();
      $entityId = $entity->id();
      $field_definition = $items->getFieldDefinition();
      $field_name = $items->getName();
      $hasPermission = $this->checkPermissionOperation($entity, $field_name);
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

}
