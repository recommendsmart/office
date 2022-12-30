(function (Drupal, $, drupalSettings, once) {
  Drupal.behaviors.highChart = {
    attach: function (context, settings) {
      $.each(settings.highChart, function (selector) {
        $(once('highChart', selector, context)).each(function () {
          // Check if table contains expandable hidden rows.
          var options = drupalSettings.highChart[selector]['options'];
          var type = drupalSettings.highChart[selector]['type'];
          var dataTable = drupalSettings.highChart[selector]['data'];
          Highcharts.chart(selector, dataTable);
        });
      });
    }
  };

}(Drupal, jQuery, drupalSettings, once));
