(function ($, Drupal) {
  Drupal.AjaxCommands.prototype.fieldSuggestion = function (ajax, response, status) {
    var value = $('[data-drupal-selector="edit-' + response.name + '-0-suggestion"]')
      .data('suggestion-' + response.delta);

    response.command = 'invoke';
    response.selector = '#edit-' + response.name + '-0-' + response.property;
    response.method = 'val';
    response.args = [value];
    ajax.commands.invoke(ajax, response, status);
  };
})(jQuery, Drupal);
