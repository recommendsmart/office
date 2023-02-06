(function (Drupal, $, once) {
  Drupal.behaviors.datatables = {
    attach: function (context, settings) {
      $.each(settings.datatables, function (selector) {
        $(once('datatables', selector, context)).each(function () {
          // Check if table contains expandable hidden rows.
          var settings = drupalSettings.datatables[selector];

          if (settings.bStateSave) {
            settings.fnStateLoaded = function (oSettings, oData) {
              settings.searchCols_data = oData.columns;
            }
          }
          var datatable = $(selector).DataTable(settings);

          if (settings.buttons && typeof datatable.buttons === "function") {
            var conntainer = datatable.buttons().container();
            conntainer.appendTo(selector + '_wrapper .col-sm-6:eq(0)');
          }
          // end Buttons

          if (settings.bMultiFilter) {
            // Setup - add a text input to each footer cell
            var fPosition = settings.bMultiFilter_position;
            let position = {
              'header': 'thead',
              'footer': 'tfoot'
            };
            $(selector + ' ' + position[fPosition] + ' th').each(function () {
              var title = $(this).text();
              $(this).html('<input class="form-control form-control-sm" type="text" placeholder="' + title + '" />');
            });

            // The code below is taken almost verbatim from the examples at
            // http://www.datatables.net/examples/api/multi_filter.html

            datatable.columns().every(function (index) {
              var that = this;

              $('input,select', this[fPosition]()).on('keyup change', function () {
                if (that.search() !== this.value) {
                  that
                    .search(this.value)
                    .draw();
                }
              });
              //stop ordering with mutil filter
              if (settings.bMultiFilter_position == "header") {
                $('input,select', this[fPosition]()).click(function (event) {
                  event.stopPropagation();
                });
              }
              //load value if stateSave is on
              if (settings.bStateSave && settings.searchCols_data) {
                var value_search = settings.searchCols_data[index].search['search'];
                if (value_search.trim()) {
                  $('input,select', this[fPosition]()).val(value_search);
                }
              }
            });
          }
          // end bMultiFilter
        });
      });
    }
  };

}(Drupal, jQuery, once));
