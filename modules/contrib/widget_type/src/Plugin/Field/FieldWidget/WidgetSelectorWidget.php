<?php

namespace Drupal\widget_type\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Defines the 'widget_type_widget_selector' field widget.
 *
 * @FieldWidget(
 *   id = "widget_type_widget_selector",
 *   label = @Translation("Widget Selector"),
 *   field_types = {"entity_reference"},
 *   multiple_values = TRUE
 * )
 */
class WidgetSelectorWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    array $element,
    array &$form,
    FormStateInterface $form_state
  ) {
    $item = $items->get($delta);
    $default_value = $item ? $item->getValue() : NULL;
    $build = $element + [
      '#type' => 'widget_type_selector',
      '#default_value' => $default_value,
    ];
    // We don't support editing the widget type after the fact, it can cause
    // unforeseen consequences on display options.
    if (isset($default_value['target_id'])) {
      $target_id = $default_value['target_id'];
      $build['#options'] = array_combine([$target_id], [$target_id]);
      $build['additional_info'] = [
        '#markup' => $this->t('The widget type <strong>should not be changed</strong> after creation. The available options have been reduced to the current widget type.'),
      ];
    }
    // We only support single values.
    return [$build];
  }

}
