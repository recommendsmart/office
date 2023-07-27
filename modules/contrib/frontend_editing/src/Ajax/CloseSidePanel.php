<?php

namespace Drupal\frontend_editing\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * AJAX command for closing the side panel.
 *
 * This command is implemented in Drupal.AjaxCommands.prototype.closeSidePanel()
 * defined in frontend_editing/js/frontend_editing.js.
 *
 * @ingroup ajax
 */
class CloseSidePanel implements CommandInterface {

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'closeSidePanel',
    ];
  }

}
