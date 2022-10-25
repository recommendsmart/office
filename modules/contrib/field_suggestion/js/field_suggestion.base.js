(function ($, Drupal) {
  Drupal.behaviors.field_suggestion = {
    attach: function attach(context) {
      var $context = $(context);

      $context.find('.vertical-tabs__menu-item a').each(function () {
        var href = $(this).attr('href');

        $context.find(href).drupalSetSummary(function (context) {
          var fields = [];
          var prefix = href.substring(href.indexOf('-') + 1).replace('-', '_');

          $(context)
            .find('input[name^="' + prefix + '"]:checked')
            .next('label')
            .each(function () {
              fields.push(Drupal.checkPlain($(this).text()));
            });

          return fields.join(', ');
        });
      });
    }
  };
})(jQuery, Drupal);
