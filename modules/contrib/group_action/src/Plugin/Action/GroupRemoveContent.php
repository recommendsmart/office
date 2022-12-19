<?php

namespace Drupal\group_action\Plugin\Action;

/**
 * Action plugin for removing content from a group.
 *
 * @Action(
 *   id = "group_remove_content",
 *   label = "Group: remove content",
 *   type = "node",
 *   deriver = "Drupal\group_action\Plugin\Action\GroupActionDeriver"
 * )
 *
 * @TODO, support multiple entity types once core is fixed.
 * @see https://www.drupal.org/node/2011038
 */
class GroupRemoveContent extends GroupActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operation' => 'delete',
      'content_plugin' => '',
      'group_id' => '',
      'entity_id' => '',
      'values' => '',
    ];
  }

}
