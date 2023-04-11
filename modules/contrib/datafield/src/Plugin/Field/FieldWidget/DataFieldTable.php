<?php

namespace Drupal\datafield\Plugin\Field\FieldWidget;

use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldFilteredMarkup;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'data_field' widget.
 *
 * @FieldWidget(
 *   id = "data_field_table_widget",
 *   label = @Translation("Data Field Table"),
 *   field_types = {"data_field"}
 * )
 */
class DataFieldTable extends DataField {

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = parent::settingsForm($form, $form_state);
    if (!empty($element['inline'])) {
      unset($element['inline']);
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $field_settings = $this->getFieldSetting('field_settings');
    $storage = $this->getFormElement($items, $delta, $element, $form, $form_state);
    $widget = [
      '#type' => 'table',
      '#title' => $element["#title"],
      '#caption' => $element["#title"],
    ];
    $isHeader = FALSE;
    $header = [];
    $subfields = $this->getFieldSetting("columns");
    if (!empty($setting = $this->getSetting('widget_settings'))) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = $setting;
    }
    foreach (array_keys($subfields) as $subfield) {
      $item = $this->getFieldSetting("columns")[$subfield];
      if (!$isHeader) {
        $isHeader = TRUE;
      }
      if (!empty($setting[$subfield]) && empty($setting[$subfield]["field_display"])) {
        continue;
      }
      if (!empty($setting[$subfield]) && $setting[$subfield]["label_display"] == 'hidden') {
        $header[] = '';
        continue;
      }
      $header[] = $field_settings[$subfield]["label"] ?? $item['name'];
    }
    if (!empty($isHeader)) {
      $widget['#header'] = $header;
    }
    return $element + $widget + $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {
    $field_name = $this->fieldDefinition->getName();
    $settings = $this->getSetting('widget_settings');
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()
      ->getCardinality();
    $parents = $form['#parents'];
    $elements = [];
    switch ($cardinality) {
      case FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED:
        $field_state = static::getWidgetState($parents, $field_name, $form_state);
        $max = $field_state['items_count'];
        $is_multiple = TRUE;
        break;

      default:
        $max = $cardinality - 1;
        $is_multiple = ($cardinality > 1);
        break;
    }

    $title = $this->fieldDefinition->getLabel();
    $description = FieldFilteredMarkup::create(\Drupal::token()
      ->replace($this->fieldDefinition->getDescription()));
    $header = $is_multiple ? [['data' => ['#markup' => '']]] : [];
    $field_settings = $this->getFieldSetting('field_settings');
    $storages = $this->getFieldSetting('columns');
    $order_class = $field_name . '-delta-order';
    foreach ($storages as $subfield => $item) {
      if (!empty($settings[$subfield])) {
        if (empty($settings[$subfield]["field_display"])) {
          continue;
        }
        if ($settings[$subfield]["label_display"] == 'hidden') {
          $header[] = ['data' => ['#markup' => '']];
          continue;
        }
      }
      $header[] = $field_settings[$subfield]["label"] ?? $item['name'];
    }
    foreach (range(0, $max) as $delta) {
      if (empty($items[$delta])) {
        $items->appendItem();
      }
      if ($is_multiple) {
        $element = [
          '#title' => $this->t('@title (value @number)',
            ['@title' => $title, '@number' => $delta + 1]),
          '#title_display' => 'invisible',
          '#description' => '',
        ];
      }
      else {
        $element = [
          '#title' => $title,
          '#caption' => $title,
          '#title_display' => 'before',
          '#description' => $description,
        ];
      }
      $element = $this->formSingleElement($items, $delta, $element, $form, $form_state);
      if (!empty($element)) {
        $element[$delta]['#attributes']['class'][] = 'draggable';
        $element[$delta]['#weight'] = $items[$delta]->_weight ?: $delta;
        if ($is_multiple) {
          $element[$delta]['_weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight for row @number', ['@number' => $delta + 1]),
            '#title_display' => 'invisible',
            '#delta' => $max,
            '#attributes' => ['class' => [$order_class]],
            '#default_value' => $items[$delta]->_weight ?: $delta,
            '#weight' => 100,
          ];
          array_unshift($element[$delta], ['data' => ['#markup' => '']]);
        }
        $elements[$delta] = $element[$delta];
      }
    }
    if (!empty($elements)) {
      $elements += [
        '#header' => $header,
        '#field_name' => $field_name,
        '#cardinality' => $cardinality,
        '#cardinality_multiple' => $this->fieldDefinition->getFieldStorageDefinition()
          ->isMultiple(),
        '#required' => $this->fieldDefinition->isRequired(),
        '#title' => $title,
        '#title_display' => 'before',
        '#description' => $description,
        '#max_delta' => $max,
        '#widgetDataFieldTable' => TRUE,
        '#empty' => $this->t('There are no widgets.'),
      ];
      if ($is_multiple) {
        $header[] = $this->t('Order', [], ['context' => 'Sort order']);
        $header[0] = ['data' => ['#markup' => '']];
        $elements['#theme'] = 'field_multiple_value_form';
        $elements['#tabledrag'] = [
          [
            'action' => 'order',
            'relationship' => 'sibling',
            'group' => $order_class,
          ],
        ];
      }
      else {
        $elements['#type'] = 'table';
        unset($elements['#theme']);
      }
      if (!empty($header)) {
        $elements['#header'] = $header;
      }
      // Add an "add more" button if it doesn't work with a programmed form.
      if ($cardinality == FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED && !$form_state->isProgrammed()) {
        $id_prefix = implode('-', array_merge($parents, [$field_name]));
        $wrapper_id = Html::getUniqueId($id_prefix . '-add-more-wrapper');
        $elements['#prefix'] = '<div id="' . $wrapper_id . '">';
        $elements['#suffix'] = '</div>';
        $elements['add_more'] = [
          '#type' => 'submit',
          '#name' => strtr($id_prefix, '-', '_') . '_add_more',
          '#value' => $this->t('Add another item'),
          '#attributes' => [
            'class' => ['field-add-more-submit', 'out-of-table'],
            'data-ajaxTo' => $wrapper_id,
          ],
          '#wrapper_attributes' => ['colspan' => count($header)],
          '#limit_validation_errors' => [array_merge($parents, [$field_name])],
          '#submit' => [[get_class($this), 'addMoreSubmit']],
          '#ajax' => [
            'callback' => [get_class($this), 'addMoreAjax'],
            'wrapper' => $wrapper_id,
            'effect' => 'fade',
          ],
          '#element_parents' => $parents,
        ];
      }
      $elements['#attached']['library'] = ['datafield/paste_clipboard'];
    }

    return $elements;
  }

}
