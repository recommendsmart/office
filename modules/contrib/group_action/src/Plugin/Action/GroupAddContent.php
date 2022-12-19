<?php

namespace Drupal\group_action\Plugin\Action;

/**
 * Action plugin for adding content to a group.
 *
 * @Action(
 *   id = "group_add_content",
 *   label = "Group: add content",
 *   type = "node",
 *   deriver = "Drupal\group_action\Plugin\Action\GroupActionDeriver"
 * )
 *
 * @TODO, support multiple entity types once core is fixed.
 * @see https://www.drupal.org/node/2011038
 */
class GroupAddContent extends GroupActionBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operation' => 'create',
      'content_plugin' => '',
      'group_id' => '',
      'entity_id' => '',
      'values' => '',
      'add_method' => '',
    ];
  }

}
