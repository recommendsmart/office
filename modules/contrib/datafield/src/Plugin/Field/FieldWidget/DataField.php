<?php

namespace Drupal\datafield\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'data_field' widget.
 *
 * @FieldWidget(
 *   id = "data_field_widget",
 *   label = @Translation("Data Field"),
 *   field_types = {"data_field"}
 * )
 */
class DataField extends Base {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {

    $field_settings = $this->getFieldSettings();
    $storage = $this->getFormElement($items, $delta, $element, $form, $form_state);
    $settings = $this->getSettings();
    $widget = [
      '#theme_wrappers' => ['container', 'form_element'],
      '#attributes' => ['class' => [Html::getId('data_field-elements')]],
      '#attached' => [
        'library' => ['datafield/widget'],
      ],
    ];

    if ($settings['inline']) {
      $widget['#attributes']['class'][] = Html::getId('data_field-widget-inline');
    }

    foreach ($storage[$delta] as $subfield => $item) {
      $widget[$subfield] = $item;
      $widget_type = $item['#type'];
      $label_display = $field_settings["field_settings"][$subfield]['label_display'] ?? 'hidden';
      $label = $field_settings["field_settings"][$subfield]['label'];
      if ($label_display != 'hidden' && parent::isLabelSupported($widget_type)) {
        $widget[$subfield]['#title'] = $label;
        if ($label_display == 'invisible') {
          $widget[$subfield]['#title_display'] = 'invisible';
        }
        elseif ($label_display == 'inline') {
          $widget[$subfield]['#wrapper_attributes']['class'][] = 'container-inline';
        }
      }

    }

    return $element + $widget;
  }

}
