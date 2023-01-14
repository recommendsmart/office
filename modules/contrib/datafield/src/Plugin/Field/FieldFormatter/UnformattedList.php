<?php

namespace Drupal\datafield\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;

/**
 * Plugin implementations for 'data_field' formatter.
 *
 * @FieldFormatter(
 *   id = "data_field_unformatted_list",
 *   label = @Translation("Unformatted list"),
 *   field_types = {"data_field"}
 * )
 */
class UnformattedList extends ListBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    $element['#attributes']['class'][] = 'data-field-unformatted-list';
    if (!empty($this->getSetting('custom_class'))) {
      $element['#attributes']['class'][] = $this->getSetting('custom_class');
    }
    $settings = $this->getSettings();
    $field_name = $items->getName();
    foreach ($items as $delta => $item) {
      if ($settings['inline']) {
        if (!isset($item->_attributes)) {
          $item->_attributes = [];
        }
        $item->_attributes += ['class' => ['container-inline']];
      }
      $element[$delta] = [
        '#theme' => 'data_field_item',
        '#settings' => $settings,
        '#field_settings' => $this->getFieldSettings(),
        '#field_name' => $field_name,
        '#item' => $item,
      ];
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
