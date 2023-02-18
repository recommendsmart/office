(function ($, Drupal) {
  Drupal.AjaxCommands.prototype.fieldSuggestion = function (ajax, response, status) {
    var $list = $('[data-drupal-selector="edit-' + response.name + '-0-suggestion"]');
    var value = $list.data('suggestion-' + response.delta);

    response.command = 'invoke';
    response.selector = '#edit-' + response.name + '-0-' + response.property;
    response.method = 'val';
    response.args = [value];
    ajax.commands.invoke(ajax, response, status);

    if (Drupal.DropButton !== undefined) {
      Drupal.DropButton.dropbuttons.forEach(function (DropButton) {
        DropButton.close();
      });
    }
    else if ($list.hasClass('dropdown')) {
      $list.dropdown('toggle');
    }
  };
})(jQuery, Drupal);
