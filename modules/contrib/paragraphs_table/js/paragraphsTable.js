(function (Drupal, $, once) {
  Drupal.behaviors.paragraphsTable = {
    attach: function (context, settings) {
      $.each(settings.paragraphsTable, function (selector) {
        $(once('paragraphsTable', selector, context)).each(function () {
          const settings = drupalSettings.paragraphsTable[selector];
          $.getJSON( settings['url'], function ( data ) {
            let row,tbody = [];
            // loop through all the rows, we will deal with tfoot and thead later
            $.each( data, function ( rowIndex, valRow ) {
              row = $('<tr />');
              $.each( valRow, function ( colIndex, valCol ) {
                row.append($('<td />').html(valCol));
              });
              tbody.push(row);
            });
            $('#' + settings['id'] ).append($('<tbody />').append(tbody));
          });
        });
      });
    }
  };

}(Drupal, jQuery, once));
