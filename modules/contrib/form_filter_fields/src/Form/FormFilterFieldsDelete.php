<?php

namespace Drupal\form_filter_fields\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class FormFilterFieldsDelete. The delete functionality for this module.
 *
 * @package Drupal\form_filter_fields\Form
 */
class FormFilterFieldsDelete extends ConfirmFormBase {

  /**
   * The data type.
   *
   * @var dataType
   */
  protected $dataType;

  /**
   * The machine name of the content type.
   *
   * @var contentTypeMachineName
   */
  protected $contentTypeMachineName;

  /**
   * The machine name of the control field.
   *
   * @var controlField
   */
  protected $controlField;

  /**
   * The machine name of the target field.
   *
   * @var targetField
   */
  protected $targetField;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "form_filter_fields_delete_form";
  }

  /**
   * The prompt.
   */
  public function getQuestion() {
    return t(
      "Are you sure you want to delete the @targetField dependency?",
      ["@targetField" => $this->targetField]
    );
  }

  /**
   * The confirmation text.
   */
  public function getConfirmText() {
    return t("Delete");
  }

  /**
   * The cancel URL to go to.
   */
  public function getCancelUrl() {
    return new Url("form_filter_fields.settings");
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $dataType = "", $contentTypeMachineName = "", $controlField = "", $targetField = "") {

    // Data comes in like {contentTypeMachineName}/{controlField}/{targetField}.
    if (!isset($dataType) || !isset($contentTypeMachineName) || !isset($controlField) || !isset($targetField)) {
      throw new NotFoundHttpException();
    }

    $this->dataType = $dataType;
    $this->contentTypeMachineName = $contentTypeMachineName;
    $this->controlField = $controlField;
    $this->targetField = $targetField;

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // For some reason deleting a specific variable from our configuration
    // does not work what we do here is get all the configuration, then delete
    // it all, unset what the user deleted, then save it all again.
    $config = $this->config("form_filter_fields.settings");
    $fff_dependencies = $config->get("form_filter_fields_settings");

    // Make sure what they want to delete is real.
    if (isset($fff_dependencies[$this->dataType][$this->contentTypeMachineName][$this->controlField][$this->targetField])) {
      unset($fff_dependencies[$this->dataType][$this->contentTypeMachineName][$this->controlField][$this->targetField]);

      // Now delete all the config from this.
      \Drupal::configFactory()->getEditable("form_filter_fields.settings")->delete();

      // Then loop through the array and create it again.
      foreach ($fff_dependencies as $dataType => $form_filter_field_dependenceies) {
        foreach ($form_filter_field_dependenceies as $contentTypeMachineName => $dependent_field_info) {
          // Now loop through all the depend fields of that content type.
          foreach ($dependent_field_info as $controlField => $targetFields) {
            // Since a control field can have many target fields, loop through
            // all the target fields.
            foreach ($targetFields as $targetField => $viewId) {
              // Recreate the configuration.
              \Drupal::configFactory()->getEditable("form_filter_fields.settings")
                ->set("form_filter_fields_settings." . $dataType . "." . $contentTypeMachineName . "." . $controlField . "." . $targetField, $viewId)
                ->save();
            }
          }
        }
      }
    }

    // Forward them back to the settings form.
    $this->messenger()->addStatus($this->t("Dependency deleted."));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
