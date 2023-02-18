(function (Drupal, $, once) {
  Drupal.behaviors.googlecharts = {
    attach: function (context, settings) {
      $.each(settings.googleCharts, function (selector) {
        $(once('googleCharts', selector, context)).each(function () {
          // Check if table contains expandable hidden rows.
          const options = drupalSettings.googleCharts[selector]['options'];
          const type = drupalSettings.googleCharts[selector]['type'];
          const url = drupalSettings.googleCharts[selector]['options']['url'];
          if (!url) {
            google.charts.load("current", {packages: ["corechart"]});
            let dataTable = drupalSettings.googleCharts[selector]['data'];
            google.charts.setOnLoadCallback(drawChart);

            function drawChart() {
              let data = google.visualization.arrayToDataTable(dataTable);
              let view = new google.visualization.DataView(data);
              let chart = new google.visualization[type](document.getElementById(selector.replace(/#/i, "")));
              chart.draw(view, options);
            }
          } else {
            $.ajax({
              url: 'https://www.google.com/jsapi?callback',
              cache: true,
              dataType: 'script',
              success: function () {
                google.load('visualization', '1', {
                  packages: ['corechart'],
                  callback: function () {
                    $.ajax({
                      type: "GET",
                      dataType: "json",
                      data: {id: selector},
                      url: url,
                      success: function (jsonData) {
                        const data = google.visualization.arrayToDataTable(jsonData);
                        let chart = new google.visualization[type](document.getElementById(selector.replace(/#/i, "")));
                        chart.draw(data, options);
                      }
                    });
                  }
                });
                return true;
              }
            });
          }
        });
      });
    }
  };

}(Drupal, jQuery, once));
