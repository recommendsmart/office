<?php

namespace Drupal\eca_form\Plugin\Action;

/**
 * Set a form field as disabled.
 *
 * @Action(
 *   id = "eca_form_field_disable",
 *   label = @Translation("Form field: set as disabled"),
 *   description = @Translation("Disable a form field."),
 *   type = "form"
 * )
 */
class FormFieldDisable extends FormFlagFieldActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getFlagName(bool $human_readable = FALSE) {
    return $human_readable ? $this->t('disabled') : 'disabled';
  }

}
