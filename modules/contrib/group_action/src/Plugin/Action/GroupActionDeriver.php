<?php

namespace Drupal\group_action\Plugin\Action;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deriver for group actions.
 */
class GroupActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Flag indicating whether the used version of Group is v2 or greater.
   *
   * @var bool|null
   */
  static protected ?bool $isV2 = NULL;

  /**
   * The plugin manager of group relation types.
   *
   * @var \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface|\Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $gcePluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    if (!isset(self::$isV2)) {
      self::$isV2 = $container->has('group_relation_type.manager');
    }
    return new static(self::$isV2 ? $container->get('group_relation_type.manager') : $container->get('plugin.manager.group_content_enabler'));
  }

  /**
   * Constructs a new GroupActionDeriver object.
   *
   * @param \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface|\Drupal\group\Plugin\GroupContentEnablerManagerInterface $gce_plugin_manager
   *   The plugin manager of group relation types.
   */
  public function __construct($gce_plugin_manager) {
    $this->gcePluginManager = $gce_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (!isset($this->derivatives) || empty($this->derivatives)) {
      $this->derivatives = [];
      $this->derivatives[''] = $base_plugin_definition;
      /** @var \Drupal\group\Plugin\Group\Relation\GroupRelationType $definition */
      foreach ($this->gcePluginManager->getDefinitions() as $definition) {
        $entity_type_id = self::$isV2 ? $definition->getEntityTypeId() : ($definition['entity_type_id'] ?? NULL);
        if (NULL === $entity_type_id) {
          continue;
        }
        if ('user' === $entity_type_id) {
          // Skip the user membership, as this does not have any bundles.
          continue;
        }
        $label = new TranslatableMarkup('@label (@type only)', [
          '@label' => $base_plugin_definition['label'],
          '@type' => $entity_type_id,
        ]);
        $this->derivatives[$entity_type_id] = [
          'type' => $entity_type_id,
          'label' => $label,
        ] + $base_plugin_definition;
      }
    }
    return $this->derivatives;
  }

}
