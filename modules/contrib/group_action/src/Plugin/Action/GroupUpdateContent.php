<?php

namespace Drupal\group_action\Plugin\Action;

/**
 * Action plugin for updating content of a group.
 *
 * @Action(
 *   id = "group_update_content",
 *   label = "Group: update content",
 *   type = "node",
 *   deriver = "Drupal\group_action\Plugin\Action\GroupActionDeriver"
 * )
 *
 * @TODO, support multiple entity types once core is fixed.
 * @see https://www.drupal.org/node/2011038
 */
class GroupUpdateContent extends GroupActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operation' => 'update',
      'content_plugin' => '',
      'group_id' => '',
      'entity_id' => '',
      'values' => '',
    ];
  }

}
