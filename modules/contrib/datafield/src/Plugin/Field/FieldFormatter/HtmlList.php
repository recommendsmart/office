<?php

namespace Drupal\datafield\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Plugin implementations for 'html_list' formatter.
 *
 * @FieldFormatter(
 *   id = "data_field_html_list",
 *   label = @Translation("Html list"),
 *   field_types = {"data_field"}
 * )
 */
class HtmlList extends ListBase {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return ['list_type' => 'ul'] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element['list_type'] = [
      '#type' => 'radios',
      '#title' => $this->t('List type'),
      '#options' => [
        'ul' => $this->t('Unordered list'),
        'ol' => $this->t('Ordered list'),
        'dl' => $this->t('Definition list'),
      ],
      '#default_value' => $this->getSetting('list_type'),
    ];

    $element += parent::settingsForm($form, $form_state);
    $field_name = $this->fieldDefinition->getName();

    $element['inline']['#states']['invisible'] = [":input[name='fields[$field_name][settings_edit_form][settings][list_type]']" => ['value' => 'dl']];
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $parent_summary = parent::settingsSummary();

    // Definition list does not support 'inline' option.
    $list_type = $this->getSetting('list_type');
    if ($list_type == 'dl') {
      if (($key = array_search($this->t('Display as inline element'), $parent_summary)) !== FALSE) {
        unset($parent_summary[$key]);
      }
    }

    $summary[] = $this->t('List type: @list_type', ['@list_type' => $this->getSetting('list_type')]);
    return array_merge($summary, $parent_summary);
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();
    $field_name = $items->getName();

    if ($settings['list_type'] == 'dl') {
      $element[0] = [
        '#theme' => 'data_field_definition_list',
        '#items' => $items,
        '#settings' => $settings,
        '#field_settings' => $field_settings,
        '#field_name' => $field_name,
      ];
    }
    else {
      $list_items = [];
      foreach ($items as $delta => $item) {

        $list_items[$delta] = [
          '#theme' => 'data_field_item',
          '#item' => $item,
          '#settings' => $settings,
          '#field_settings' => $field_settings,
          '#field_name' => $field_name,
        ];
        if ($settings['inline']) {
          $list_items[$delta]['#wrapper_attributes'] = [];
          if (!isset($item->_attributes)) {
            $item->_attributes = [];
          }
          $list_items[$delta]['#wrapper_attributes'] += $item->_attributes;
          $list_items[$delta]['#wrapper_attributes']['class'][] = 'container-inline';
        }
      }
      $element[0] = [
        '#theme' => 'item_list',
        '#list_type' => $settings['list_type'],
        '#items' => $list_items,
        '#context' => ['data_field' => ['field_name' => $field_name]],
      ];
    }

    $element[0]['#attributes']['class'][] = Html::getId('data_field-list');

    if (!empty($this->getSetting('custom_class'))) {
      $element[0]['#attributes']['class'][] = $this->getSetting('custom_class');
    }

    if (!empty($this->getSetting('form_format_table'))) {
      $entity = $items->getEntity();
      $entityId = $entity->id();
      $field_definition = $items->getFieldDefinition();
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
