<?php

namespace Drupal\entity\Plugin\Action\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a delete action for each content entity type.
 *
 * @deprecated
 */
class DeleteActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new DeleteActionDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static($container->get('entity_type.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if (empty($this->derivatives)) {
      $definitions = [];
      foreach ($this->getParticipatingEntityTypes() as $entity_type_id => $entity_type) {
        $definition = $base_plugin_definition;
        $definition['label'] = $this->t('Delete @entity_type (Deprecated)', ['@entity_type' => $entity_type->getSingularLabel()]);
        $definition['type'] = $entity_type_id;
        $definition['confirm_form_route_name'] = 'entity.' . $entity_type_id . '.delete_multiple_form';
        $definitions[$entity_type_id] = $definition;
      }
      $this->derivatives = $definitions;
    }

    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Gets a list of participating entity types.
   *
   * The list consists of all content entity types with a delete-multiple-form
   * link template.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface[]
   *   The participating entity types, keyed by entity type id.
   */
  protected function getParticipatingEntityTypes() {
    $entity_types = $this->entityTypeManager->getDefinitions();
    $entity_types = array_filter($entity_types, function (EntityTypeInterface $entity_type) {
      // Core requires a "delete-multiple-confirm" form to be declared as well,
      // if it's missing, it's safe to assume that the entity type is still
      // relying on previous Entity API contrib behavior.
      return $entity_type->hasLinkTemplate('delete-multiple-form') && !$entity_type->hasHandlerClass('form', 'delete-multiple-confirm');
    });

    return $entity_types;
  }

}
