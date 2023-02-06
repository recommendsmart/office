<?php

namespace Drupal\form_filter_fields\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\views\Views;
use Drupal\Core\Url;
use Drupal\Component\Render\FormattableMarkup;

/**
 * Class FormFilterFieldsSettingsForm. The form functionality for this module.
 *
 * @package Drupal\form_filter_fields\Form
 */
class FormFilterFieldsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return "form_filter_fields_settings";
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ["form_filter_fields.settings"];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form["#tree"] = TRUE;

    // We want to build out a form with three things on it:
    // content type, taxonomy term field (parent), dependent taxonomy term
    // field (child), view to filter child based off of parent.
    // Get all the different content types in this drupal 8 installation.
    $content_types = \Drupal::entityTypeManager()->getStorage("node_type")->loadMultiple();

    // All the content types we don't wish to output.
    $excluded_content_types = [
      "openlayers_example_content",
      "feed",
      "site",
      "site_feed",
      "webform",
    ];

    $content_type_taxonomy_field_options = [];
    $content_type_field_vocabularies = [];

    // Loop through each content type.
    foreach ($content_types as $ct) {
      // See if it is not in the content types we do not wish to search in.
      if (!in_array($ct->id(), $excluded_content_types)) {
        // Get the fields of the content type we want to search for and loop
        // through the fields.
        foreach (\Drupal::service("entity_field.manager")->getFieldDefinitions("node", $ct->id()) as $field_name => $field_definition) {

          // Get what kind of field it is.
          $field_type = $field_definition->getType();

          // We only want entity references.
          if ($field_type == "entity_reference") {
            // Get the field settings.
            $field_settings = $field_definition->getSettings();

            // Only get taxonomy terms & if its target bundles are set (what
            // vocabulary the field has).
            if (($field_settings["target_type"] == "taxonomy_term") && (isset($field_settings["handler_settings"]["target_bundles"]))) {
              // This will be the option for the select menus for the parent
              // and child.
              $option_key = "content|" . $ct->id() . "|" . $field_name;

              $content_type_taxonomy_field_options[$option_key] = $ct->id() . " - " . $field_definition->getLabel();

              // This array makes the table more readable for relationships.
              $content_type_field_vocabularies[$ct->id()][$field_name] = $field_definition->getLabel() . " <br />(vocabularies: " . implode(", ", $field_settings["handler_settings"]["target_bundles"]) . ")";
            }
          }
        }
      }
    }

    $media_exists = FALSE;
    $media_type_taxonomy_field_options = [];
    $media_type_field_vocabularies = [];

    // Check to see if media exists because some sites might not have it
    // enabled.
    $moduleHandler = \Drupal::service("module_handler");
    if ($moduleHandler->moduleExists("media")) {
      $media_exists = TRUE;

      // Get all the different media types in this drupal 8 installation.
      $media_types = \Drupal::entityTypeManager()->getStorage("media_type")->loadMultiple();

      // Loop through each media type.
      foreach ($media_types as $mt) {
        // Get the fields of the media type we want to search for & loop
        // through the fields.
        foreach (\Drupal::service("entity_field.manager")->getFieldDefinitions("media", $mt->id()) as $field_name => $field_definition) {

          // Get what kind of field it is.
          $field_type = $field_definition->getType();

          // We only want entity references.
          if ($field_type == "entity_reference") {
            // Get the field settings.
            $field_settings = $field_definition->getSettings();

            // Only get taxonomy terms & if its target bundles are set
            // (what vocabulary the field has).
            if (($field_settings["target_type"] == "taxonomy_term") && (isset($field_settings["handler_settings"]["target_bundles"]))) {
              // This will be the option for the select menus for the parent
              // and child.
              $option_key = "media|" . $mt->id() . "|" . $field_name;

              $media_type_taxonomy_field_options[$option_key] = $mt->id() . " - " . $field_definition->getLabel();

              // This array makes the table more readable for relationships.
              $media_type_field_vocabularies[$mt->id()][$field_name] = $field_definition->getLabel() . " <br />(vocabularies: " . implode(", ", $field_settings["handler_settings"]["target_bundles"]) . ")";
            }
          }
        }
      }
    }

    // Get a list of views the user can choose from. This will be the view
    // that does the filtering.
    $views_on_site = Views::getViewsAsOptions();

    // *************************************
    // Output Table
    // *************************************
    // Create table headings for the content portion.
    $content_output_table_header = [
      t("Content Type Machine Name"),
      t("Control Field"),
      t("Target Field"),
      t("View"),
      t("Operations"),
    ];

    // Output the contents of all the content type field dependencies.
    $form["form_filter_fields_table"]["intro_content"] = [
      "#type" => "container",
      "#markup" => "<h2>Content Type Form Filter Field Dependencies</h2>",
    ];

    $form["form_filter_fields_table"]["table_content"] = [
      "#type" => "table",
      "#header" => $content_output_table_header,
      "#rows" => $this->printTable($content_type_field_vocabularies, "content"),
      "#empty" => t("No dependencies found."),
    ];

    if ($media_exists) {
      // Create table headings for the media portion.
      $media_output_table_header = [
        t("Media Type Machine Name"),
        t("Control Field"),
        t("Target Field"),
        t("View"),
        t("Operations"),
      ];

      // Output the contents of all the content type field dependencies.
      $form["form_filter_fields_table"]["intro_media"] = [
        "#type" => "container",
        "#markup" => "<br /><br /><h2>Media Type Form Filter Field Dependencies</h2>",
      ];

      $form["form_filter_fields_table"]["table_media"] = [
        "#type" => "table",
        "#header" => $media_output_table_header,
        "#rows" => $this->printTable($media_type_field_vocabularies, "media"),
        "#empty" => t("No dependencies found."),
      ];
    }

    // *************************************
    // Form
    // *************************************
    $form["form_filter_fields_settings"]["intro"] = [
      "#type" => "container",
      "#markup" => "<br /><br /><h2>Instructions</h2>" .
      "<p>Configure taxonomy relationships by their fields inside each content type. Choose a Data Type then choose a Control Field (the field that controls the target) and a Target Field (the dependent field). Also select which view is used to filter this relationship.</p>" .
      "<p>Hit the <strong>Save Configuration</strong> when you're done selecting.</p>",
    ];

    $field_options = ["content" => $content_type_taxonomy_field_options];

    if ($media_exists) {
      $field_options["media"] = $media_type_taxonomy_field_options;
    }

    $form["form_filter_fields_settings"]["add_relationship_form"]["control_field"] = [
      "#title" => t("Control Field"),
      "#type" => "select",
      "#required" => TRUE,
      "#description" => t("The field that controls the Target Field."),
      "#options" => $field_options,
    ];

    $form["form_filter_fields_settings"]["add_relationship_form"]["target_field"] = [
      "#title" => t("Target Field"),
      "#type" => "select",
      "#required" => TRUE,
      "#description" => t("The field which is targeted. This field will be the one that filters down based off of the Control Field."),
      "#options" => $field_options,
    ];

    $form["form_filter_fields_settings"]["add_relationship_form"]["filtering_view"] = [
      "#title" => t("Filtering View"),
      "#type" => "select",
      "#required" => TRUE,
      "#description" => t("Select the view that will filter the Target Field based off the value in the Control Field."),
      "#options" => $views_on_site,
    ];

    return parent::buildForm($form, $form_state);

    // End of buildForm.
  }

  // -------------------------------------------------------------------

  /**
   * Validate the results.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // We need to see if the control field and the target field are using the
    // same content type and that they are not the same field.
    $values = $form_state->getValues();

    // Explode the inputs.
    $control_field_tokens = explode("|", $values["form_filter_fields_settings"]["add_relationship_form"]["control_field"]);
    $target_field_tokens = explode("|", $values["form_filter_fields_settings"]["add_relationship_form"]["target_field"]);

    if ($control_field_tokens[0] != $target_field_tokens[0]) {
      // Not the same data type so throw an error.
      $form_state->setErrorByName("control_field", $this->t("The Control Field and the Target Field must use the same data type."));
    }

    if ($control_field_tokens[1] != $target_field_tokens[1]) {
      // Not the same content type so throw an error.
      $form_state->setErrorByName("control_field", $this->t("The Control Field and the Target Field must use the same content/media type."));
    }

    if ($control_field_tokens[2] == $target_field_tokens[2]) {
      // They are using the same field so throw an error.
      $form_state->setErrorByName("control_field", $this->t("The Control Field and the Target Field must be different fields."));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    // Explode the inputs.
    $control_field_tokens = explode("|", $values["form_filter_fields_settings"]["add_relationship_form"]["control_field"]);
    $target_field_tokens = explode("|", $values["form_filter_fields_settings"]["add_relationship_form"]["target_field"]);

    // Data is like: "media|audio|field_audiences"
    // The first two indexes are the data type and the content type.
    $data_type = $control_field_tokens[0];
    $content_type = $control_field_tokens[1];

    // The third index is what fields we're controlling.
    $control_field = $control_field_tokens[2];
    $target_field = $target_field_tokens[2];

    // Save the configuration.
    \Drupal::configFactory()->getEditable("form_filter_fields.settings")
      ->set("form_filter_fields_settings." . $data_type . "." . $content_type . "." . $control_field . "." . $target_field, $values["form_filter_fields_settings"]["add_relationship_form"]["filtering_view"])
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Returns the rows of an output table.
   */
  protected function printTable($field_vocabularies, $type) {
    // Get the configuration.
    $config = $this->config("form_filter_fields.settings");
    $all_form_filter_field_dependencies = $config->get("form_filter_fields_settings");

    // A row array to return.
    $output_table_rows = [];

    if (!empty($all_form_filter_field_dependencies) && isset($all_form_filter_field_dependencies[$type])) {

      // Sort by content type to be nice.
      ksort($all_form_filter_field_dependencies[$type]);

      foreach ($all_form_filter_field_dependencies[$type] as $type_machine_name => $dependent_field_info) {

        // Sort by dependent field so it's more organized.
        ksort($dependent_field_info);

        // Now loop through all the depend fields of that content type.
        foreach ($dependent_field_info as $control_field => $target_fields) {

          // Sort by the target field so it's more organized.
          ksort($target_fields);

          // Since a control field can have many target fields, loop through
          // all the target fields.
          foreach ($target_fields as $target_field => $view_id) {

            // Create the row with the information, use a format that's
            // friendly to HTML so we can use the breaks.
            $row = [];
            $row[] = $type_machine_name;
            $row[] = new FormattableMarkup($field_vocabularies[$type_machine_name][$control_field], []);
            $row[] = new FormattableMarkup($field_vocabularies[$type_machine_name][$target_field], []);
            $row[] = $view_id;

            // Create a delete link. The delete is:
            // /admin/config/content/form_filter_fields/delete/{dataType}/
            // {contentTypeMachineName}/{controlField}/{targetField}.
            $delete_link = [];
            $delete_link["delete"] = [
              "title" => t("Delete"),
              "url" => Url::fromRoute(
                "form_filter_fields.delete",
                [
                  "dataType" => $type,
                  "contentTypeMachineName" => $type_machine_name,
                  "controlField" => $control_field,
                  "targetField" => $target_field,
                ]
              ),
            ];
            $row[] = [
              "data" => [
                "#type" => "operations",
                "#links" => $delete_link,
              ],
            ];

            $output_table_rows[] = $row;
          }
        }
      }
    }

    return $output_table_rows;

    // End of printTable.
  }

  // -------------------------------------------------------------------
}
