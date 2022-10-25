<?php

namespace Drupal\eca\Plugin;

use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;

/**
 * Trait for ECA plugins making use of a form field.
 *
 * Plugins must have a "field_name" configuration key.
 */
trait FormFieldPluginTrait {

  use FormPluginTrait;

  /**
   * Whether to use form field value filters or not.
   *
   * Mostly only relevant when working with submitted input values.
   *
   * @var bool
   */
  protected bool $useFilters = TRUE;

  /**
   * Get a default configuration array regarding a form field.
   *
   * @return array
   *   The array of default configuration.
   */
  protected function defaultFormFieldConfiguration(): array {
    $default = ['field_name' => ''];
    if ($this->useFilters) {
      $default += [
        'strip_tags' => TRUE,
        'trim' => TRUE,
        'xss_filter' => TRUE,
      ];
    }
    return $default;
  }

  /**
   * Builds the configuration form regarding a form field.
   *
   * @param array $form
   *   An associative array containing the initial structure of the plugin form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form. Calling code should pass on a subform
   *   state created through \Drupal\Core\Form\SubformState::createForSubform().
   *
   * @return array
   *   The form structure.
   */
  protected function buildFormFieldConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field name'),
      '#description' => $this->t('The input name of the form field. This is mostly found in the "name" attribute of an &lt;input&gt; form element. This property supports tokens.'),
      '#default_value' => $this->configuration['field_name'],
      '#required' => TRUE,
      '#weight' => -50,
    ];
    if ($this->useFilters) {
      $form['strip_tags'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Strip tags'),
        '#default_value' => $this->configuration['strip_tags'],
        '#weight' => -10,
      ];
      $form['trim'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Trim'),
        '#default_value' => $this->configuration['trim'],
        '#weight' => -9,
      ];
      $form['xss_filter'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Filter XSS'),
        '#description' => $this->t('Additionally filters out possible cross-site scripting (XSS) text.'),
        '#default_value' => $this->configuration['xss_filter'],
        '#weight' => -8,
      ];
    }
    return $form;
  }

  /**
   * Validation handler regarding form field configuration.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function validateFormFieldConfigurationForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Submit handler regarding form field configuration.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function submitFormFieldConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['field_name'] = $form_state->getValue('field_name');
    if ($this->useFilters) {
      $this->configuration['strip_tags'] = !empty($form_state->getValue('strip_tags'));
      $this->configuration['trim'] = !empty($form_state->getValue('trim'));
      $this->configuration['xss_filter'] = !empty($form_state->getValue('xss_filter'));
    }
  }

  /**
   * Filters the given form field value with enabled filter methods.
   *
   * @param mixed &$value
   *   The value to apply filtering on.
   */
  protected function filterFormFieldValue(&$value): void {
    $config = &$this->configuration;
    if (!$config['trim'] && !$config['strip_tags'] && !$config['xss_filter']) {
      return;
    }

    if (is_array($value)) {
      array_walk_recursive($value, function (&$v) {
        $this->filterFormFieldValue($v);
      });
    }
    elseif (is_scalar($value) || is_null($value) || (is_object($value) && method_exists($value, '__toString'))) {
      $value = (string) $value;
      if ($config['trim']) {
        $value = trim($value);
      }
      if ($config['strip_tags']) {
        $value = strip_tags($value);
      }
      if ($config['xss_filter']) {
        $value = Xss::filter($value);
      }
    }
  }

  /**
   * Get a single field name as normalized array for accessing form components.
   *
   * @return array
   *   The normalized array.
   */
  protected function getFieldNameAsArray(): array {
    return array_filter(explode('[', str_replace(']', '[', $this->configuration['field_name'])), static function ($value) {
      return $value !== '';
    });
  }

  /**
   * Get the targeted form element specified by the configured form field name.
   *
   * @return array|null
   *   The target element, or NULL if not found.
   */
  protected function &getTargetElement(): ?array {
    $nothing = NULL;
    if (!($form = &$this->getCurrentForm()) || !($name_array = $this->getFieldNameAsArray())) {
      return $nothing;
    }

    $key = array_pop($name_array);
    foreach ($this->lookupFormElements($form, $key) as &$element) {
      if (empty($name_array) || (isset($element['#parents']) && $name_array === $element['#parents']) || (isset($element['#array_parents']) && $name_array === $element['#array_parents'])) {
        // Found the element due to defined parents or array_parents.
        return $element;
      }

      if (empty($name_array)) {
        continue;
      }

      // For early form builds, parents and array_parents may not be available.
      // For such a case, have another deep look into the render array.
      $lookup = NULL;
      $parents = [];
      $lookup = static function (array &$elements, array &$name_array) use (&$lookup, &$parents) {
        if ($parent = &NestedArray::getValue($elements, $name_array)) {
          $parents[] = &$parent;
        }
        else {
          foreach (Element::children($elements) as $c_key) {
            $lookup($elements[$c_key], $name_array);
          }
        }
      };
      $lookup($form, $name_array);
      foreach ($parents as &$parent) {
        if (isset($parent[$key]) && ($parent[$key] === $element)) {
          return $element;
        }
      }
      unset($parent);
    }

    return $nothing;
  }

  /**
   * Helper function to jump to the first child of an entity field in a form.
   *
   * @param array &$element
   *   The form element that may contain the child. This variable will be
   *   changed as it is being passed as reference.
   */
  protected function &jumpToFirstFieldChild(array &$element): array {
    if (isset($element['widget'])) {
      // Automatically jump to the widget form element, as it's being build
      // by \Drupal\Core\Field\WidgetBase::form().
      $element = &$element['widget'];
    }
    if (isset($element[0])) {
      // Automatically jump to the first element.
      $element = &$element[0];
    }
    // Try to get the main property name and address it if not specified
    // otherwise.
    $main_property = 'value';
    $form_object = $this->getCurrentFormState() ? $this->getCurrentFormState()->getFormObject() : NULL;
    if ($form_object instanceof EntityFormInterface) {
      $entity = $form_object->getEntity();
      if ($entity instanceof FieldableEntityInterface) {
        $name_array = $this->getFieldNameAsArray();
        $field_name = array_shift($name_array);
        if ($entity->hasField($field_name)) {
          $item_definition = $entity->get($field_name)->getFieldDefinition()->getItemDefinition();
          if ($item_definition instanceof ComplexDataDefinitionInterface) {
            $main_property = $item_definition->getMainPropertyName() ?? 'value';
          }
        }
      }
    }
    if (isset($element[$main_property])) {
      // Automatically jump to the main property key.
      $element = &$element[$main_property];
    }
    return $element;
  }

  /**
   * Helper method for ::getTargetElement() to get form element candidates.
   *
   * @param mixed &$element
   *   The current element in scope.
   * @param mixed $key
   *   The key to lookup.
   *
   * @return array
   *   The found element candidates.
   */
  protected function lookupFormElements(&$element, $key): array {
    $found = [];
    foreach (Element::children($element) as $child_key) {
      if (($child_key === $key) || (isset($element['#name']) && $element['#name'] === $key)) {
        $found[] = &$element[$child_key];
      }
      else {
        /* @noinspection SlowArrayOperationsInLoopInspection */
        $found = array_merge($found, $this->lookupFormElements($element[$child_key], $key));
      }
    }
    return $found;
  }

  /**
   * Get the submitted value specified by the configured form field name.
   *
   * @param mixed &$found
   *   (Optional) Stores a boolean whether a value was found.
   *
   * @return mixed
   *   The submitted value. May return NULL if no submitted value exists.
   */
  protected function &getSubmittedValue(&$found = NULL) {
    if (!($form_state = $this->getCurrentFormState())) {
      $nothing = NULL;
      return $nothing;
    }

    $field_name_array = $this->getFieldNameAsArray();
    $values = &$form_state->getValues();
    if (!$values) {
      $values = &$form_state->getUserInput();
    }

    $found = FALSE;
    return $this->getFirstNestedOccurrence($field_name_array, $values, $found);
  }

  /**
   * Helper method to get the first occurence of $key in the given array.
   *
   * @param array &$keys
   *   The nested keys to lookup.
   * @param array &$array
   *   The array to look into.
   * @param mixed &$found
   *   (Optional) Stores a boolean whether a value was found.
   *
   * @return mixed
   *   The found element as reference. Returns NULL if not found.
   */
  protected function &getFirstNestedOccurrence(array &$keys, array &$array, &$found = NULL) {
    $value = &NestedArray::getValue($array, $keys, $found);
    if ($found) {
      return $value;
    }
    foreach ($array as &$v) {
      if (is_array($v)) {
        $value = &$this->getFirstNestedOccurrence($keys, $v, $found);
        if ($found) {
          return $value;
        }
      }
    }
    $nothing = NULL;
    return $nothing;
  }

}
