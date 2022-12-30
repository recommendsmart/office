/**
 * @file
 * Custom JS for the JSON table field formatter.
 */
(function ($, Drupal, drupalSettings, once) {
  'use strict';
  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.data_datatables = {
    attach(context) {
      $(once('data-field-table', '.data-field-table', context))
        .each(function (index, element) {
          let fieldName = $(this).data('field--field-name');
          let config = drupalSettings.datatables[fieldName];
          $(this).DataTable(config);
        });
    }
  };

})(jQuery, Drupal, drupalSettings, once);
