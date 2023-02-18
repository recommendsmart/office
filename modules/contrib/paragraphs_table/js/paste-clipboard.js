(function (Drupal, $, once) {
  Drupal.behaviors.pasteParagraphsClipboard = {
    attach: function attach(context, settings) {
      $(once('paste-clipboard', '.paste-paragraphs-clipboard', context)).on("click", function () {
        const id_table = $(this).data("table");
        const tab = "\t";
        const breakLine = "\n";
        navigator.clipboard.readText()
          .then(text => {
            let items = text.split(breakLine);
            let data = [];
            $.each(items, function (key, val) {
              data[key] = $.trim(val).split(tab);
            });
            $('table#' + id_table + ' > tbody  > tr').each(function (row, tr) {
              if(row in data){
                let removeFirstCol = 0;
                if($(this).hasClass('draggable')){
                  removeFirstCol = 1;
                }
                $(this).find('td').each(function (col, td) {
                  col -= removeFirstCol;
                  if(col in data[row]){
                    $(this).find("input").val(data[row][col]);
                    //if select check have value
                    const exists = 0 != $(this).find('select').length;
                    if(exists){
                      let that = $(this);
                      $.each($(this).find("select").prop("options"), function (i, opt) {
                        if(opt.textContent == data[row][col] || opt.value == data[row][col]){
                          that.find("select").val(opt.value);
                        }
                      })
                    }
                  }
                });
              }
            });
          })
          .catch(() => {
            console.log('Failed to read from clipboard.');
          });
      });

      // Paste form excel to first rows.
      $('.field--widget-paragraphs-table-widget table tbody input:first').on('paste', function (e) {
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

    }
  };
}(Drupal, jQuery, once));
