<?php

namespace Drupal\entity_reference_tree\Ajax;

use Drupal\Core\Ajax\OpenModalDialogCommand;

class OpenEntityReferenceTreeModalDialogCommand extends OpenModalDialogCommand {

  /**
   * {@inheritdoc}
   */
  public function __construct($title, $content, array $dialog_options = [], $settings = NULL) {
    parent::__construct($title, $content, $dialog_options, $settings);
    $this->selector = '#entity-reference-tree-modal';
  }

}
