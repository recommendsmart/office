/**
 * @file
 * Custom JS for the JSON table field formatter.
 */
(function (Drupal, $, drupalSettings, once) {
  'use strict';
  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.datafield_json_view = {
    attach(context) {
      $(once('json-view', '.json-view', context)).each(function (index, element) {
        let fieldName = $(this).data('json-field');
        let config = drupalSettings.json_view[fieldName];
        $(this).JSONView($(this).text(), config);
      });
    }
  };

}(Drupal, jQuery, drupalSettings, once));
