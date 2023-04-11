<?php

namespace Drupal\entity_reference_tree\Ajax;

use Drupal\Core\Ajax\CloseModalDialogCommand;

class CloseEntityReferenceTreeModalDialogCommand extends CloseModalDialogCommand {

  /**
   * {@inheritdoc}
   */
  public function __construct($persist = FALSE) {
    parent::__construct($persist);
    $this->selector = '#entity-reference-tree-modal';
  }

}
