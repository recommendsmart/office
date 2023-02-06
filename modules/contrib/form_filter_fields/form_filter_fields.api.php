<?php

/**
 * @file
 * API hooks for Form Filter Fields.
 */

use Drupal\Core\Form\FormState;

/**
 * @file
 * Hooks related to form filter fields module.
 */

/**
 * @addtogroup hooks
 * @{
 */

// -------------------------------------------------------------------

/**
 * Anything that needs to stick upon form submission or entity edit.
 *
 * @param array &$form
 *   The form array.
 * @param \Drupal\Core\Form\FormState &$form_state
 *   The form_state object.
 */
function hook_form_filter_fields_load(array &$form, FormState &$form_state) {
  // Do anything with the form or form_state
  // You can figure out what content type it is with the following:
  $form_state->getFormObject()->getEntity()->bundle();
  $form["#entity_type"];
}

// -------------------------------------------------------------------

/**
 * Ability to modify other aspects of the form upon form change.
 *
 * @param array &$form
 *   The form array.
 * @param \Drupal\Core\Form\FormState &$form_state
 *   The form_state object.
 */
function hook_form_filter_fields_callback_alter(array &$form, FormState &$form_state) {
  // Do anything with the form or form_state
  // You can figure out what content type it is with the following:
  $form_state->getFormObject()->getEntity()->bundle();
  $form["#entity_type"];
}

// -------------------------------------------------------------------

/**
 * @} End of "addtogroup hooks".
 */
