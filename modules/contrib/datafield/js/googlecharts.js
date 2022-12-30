(function (Drupal, $, once) {
  Drupal.behaviors.googlecharts = {
    attach: function (context, settings) {
      $.each(settings.googleCharts, function (selector) {
        $(once('googleCharts', selector, context)).each(function () {
          // Check if table contains expandable hidden rows.
          var options = drupalSettings.googleCharts[selector]['options'];
          var type = drupalSettings.googleCharts[selector]['type'];
          google.charts.load("current", {packages: ["corechart"]});
          var dataTable = drupalSettings.googleCharts[selector]['data'];
          google.charts.setOnLoadCallback(drawChart);
          function drawChart() {
            var data = google.visualization.arrayToDataTable(dataTable);
            var view = new google.visualization.DataView(data);
            var chart = new google.visualization[type](document.getElementById(selector.replace(/#/i, "")));
            chart.draw(view, options);
          }
        });
      });
    }
  };

}(Drupal, jQuery, once));
