/**
 * @file
 * Custom JS for the JSON table field formatter.
 */
(function (Drupal, $, once) {
  'use strict';

  function parseJson(string) {
    try {
      return JSON.parse(string);
    }
    catch (e) {
      return null;
    }
  }

  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.json_editor = {
    attach(context) {
      $(once('json-editor', '.json-editor', context)).each(function (index, element) {
        let $textarea = $(this);
        let mode = $(this).data('json-editor');
        let id = $(this).data('id');
        let data = parseJson($textarea.val());
        const container = document.getElementById(id);
        const editor = new JSONEditor(container, {
          mode: mode,
          modes: ['code', 'form', 'text', 'tree'],
          onChange: function () {
            $textarea.text(editor.getText());
          }
        }, data);
      });
    }
  };

  Drupal.behaviors.json_viewer = {
    attach(context) {
      $(once('json-viewer', '.json-viewer', context)).each(function (index, element) {
        let $textarea = $(this);
        let id = $(this).data('id');
        let data = parseJson($textarea.text());
        const container = document.getElementById(id);
        const editor = new JSONEditor(container, {
          mode: 'view',
        }, data);
      });
    }
  };
}(Drupal, jQuery, once));
