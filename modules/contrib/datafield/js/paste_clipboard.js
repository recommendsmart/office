/**
 * @file
 * Paste from clipboard to table field widget.
 */

(function (Drupal, $, once) {
  'use strict';
  Drupal.behaviors.paste_clipboard = {
    attach: function (context) {
      // Initialize the Quick Edit app once per page load.
      $(once('data-field-table-widget', '.field--widget-data-field-table-widget', context)).each(function () {
        $('.field--widget-data-field-table-widget table tbody input:first').on('paste', function (e) {
          var $this = $(this);
          $.each(e.originalEvent.clipboardData.items, function (i, v) {
            if (v.type === 'text/plain') {
              v.getAsString(function (text) {
                var data = [];
                text = text.trim('\r\n');
                text.split('\r\n').forEach((v2, row) => {
                  let rows = v2.split('\t');
                  data[row] = rows;
                });
                $this.closest('tbody').find('tr').each(function (row, tr) {
                  if (row in data) {
                    let removeFirstCol = 0;
                    if ($(this).hasClass('draggable')) {
                      removeFirstCol = 1;
                    }
                    $(this).find('td').each(function (col, td) {
                      col -= removeFirstCol;
                      if (col in data[row]) {
                        $(this).find("input").val(data[row][col]);
                        //if select check have value
                        const exists = 0 != $(this).find('select').length;
                        if (exists) {
                          let that = $(this);
                          $.each($(this).find("select").prop("options"), function (i, opt) {
                            if (opt.textContent == data[row][col] || opt.value == data[row][col]) {
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
          return false;
        });

      });
    }
  };
}(Drupal, jQuery, once));
