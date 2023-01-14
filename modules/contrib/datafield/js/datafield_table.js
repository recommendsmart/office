/**
 * @file
 * Custom JS for the JSON table field formatter.
 */

(function (Drupal, $, once) {
  'use strict';

  /**
   * Attach behavior for JSON Fields.
   *
   * @type {Drupal~behavior}
   */
  Drupal.behaviors.datafield_table = {
    attach: function (context) {
      // Initialize the Quick Edit app once per page load.
      $(once('data-table-field', '.field--type-data-field.field--widget-data-field-table-widget', context))
        .each(function () {
        pasteFromClipBoard();
        function pasteFromClipBoard() {
          $('.field--type-data-field.field--widget-data-field-table-widget input').on('paste', function (e) {
            var $this = $(this);
            $.each(e.originalEvent.clipboardData.items, function (i, v) {
              if (v.type === 'text/plain') {
                v.getAsString(function (text) {
                  var tbody = $this.closest('tbody'),
                    table = [[]];
                  text = text.trim('\r\n');
                  $.each(text.split('\r\n'), function (row, v2) {
                    let rows = v2.split('\t');
                    let totalCol = rows.length;
                    table[row] = new Array(totalCol);
                    $.each(rows, function (col, v3) {
                      table[row][col] = v3;
                    });
                  });

                  tbody.find( 'tr').each(function (row, tr) {
                    if(row in table){
                      let removeFirstCol = 0;
                      if($(this).hasClass('draggable')){
                        removeFirstCol = 1;
                      }
                      $(this).find('td').each(function (col, td) {
                        col -= removeFirstCol;
                        if(col in table[row]){
                          $(this).find("input").val(table[row][col]);
                          //if select check have value
                          const exists = 0 != $(this).find('select').length;
                          if(exists){
                            let that = $(this);
                            $.each($(this).find("select").prop("options"), function (i, opt) {
                              if(opt.textContent == table[row][col] || opt.value == table[row][col]){
                                that.find("select").val(opt.value);
                              }
                            })
                          }
                        }
                      });
                    }
                  });

                });
              }
            });
            return FALSE;
          });
        }
      });
    }
  };
}(Drupal, jQuery, once));
