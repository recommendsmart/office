/**
 * @file
 * JavaScript behaviors for aggrid JSON EDITOR integration.
 */

(function ($, Drupal, once) {

    'use strict';

    /**
     * Initialize aggrid JSON editor.
     *
     * @type {Drupal~behavior}
     */
    Drupal.behaviors.aggridJsonEditor = {
        attach: function (context) {
            // Aggrid JSON editor.
            once('aggridJsonEditor', '.aggrid-json-widget', context).forEach((jsonwidget) => {
                const $jsonwidget = $(jsonwidget);
                // Get the JSON data.
                let jsonData = JSON.parse($jsonwidget.text());
                // Stringify it.
                jsonData = JSON.stringify(jsonData, undefined, 2);
                // Put it back.
                $jsonwidget.text(jsonData);
              });
        }
    };

})(jQuery, Drupal, once);
