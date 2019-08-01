<?php

namespace Drupal\if_then_else\core\Nodes\Events\AfterSavingExistingEntityEvent;

use Drupal\if_then_else\core\Nodes\Events\Event;
use Drupal\if_then_else\Event\EventConditionEvent;
use Drupal\if_then_else\Event\EventFilterEvent;
use Drupal\if_then_else\Event\NodeSubscriptionEvent;
use Drupal\if_then_else\Event\NodeValidationEvent;

/**
 * After saving existing entity event class.
 */
class AfterSavingExistingEntityEvent extends Event {

  /**
   * Return name of node.
   */
  public static function getName() {
    return 'after_saving_existing_entity_event';
  }

  /**
   * Event subscriber for after saving existing entity event node.
   */
  public function registerNode(NodeSubscriptionEvent $event) {
    // Calling custom service for if then else utilities. To
    // fetch values of entities and bundles.
    $if_then_else_utilities = \Drupal::service('ifthenelse.utilities');
    $form_entity_info = $if_then_else_utilities->getContentEntitiesAndBundles();

    $event->nodes[static::getName()] = [
      'label' => t('After Saving Existing Entity'),
      'type' => 'event',
      'class' => 'Drupal\\if_then_else\\core\\Nodes\\Events\\AfterSavingExistingEntityEvent\\AfterSavingExistingEntityEvent',
      'library' => 'if_then_else/AfterSavingExistingEntityEvent',
      'control_class_name' => 'AfterSavingExistingEntityEventControl',
      'entity_info' => $form_entity_info,
      'outputs' => [
        'entity' => [
          'label' => t('Entity'),
          'description' => t('Entity object.'),
          'socket' => 'object.entity',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateNode(NodeValidationEvent $event) {
    $data = $event->node->data;

    if (!property_exists($data, 'selection')) {
      $event->errors[] = t('Select the Match Condition in "@node_name".', ['@node_name' => $event->node->name]);
      return;
    }

    if ($data->selection == 'list' && (!property_exists($data,'selected_entity') || !property_exists($data,'selected_bundle'))) {
      // Make sure that both selected_entity and selected_bundle are set.
      $event->errors[] = t('Select both entity and bundle in "@node_name".', ['@node_name' => $event->node->name]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getConditions(EventConditionEvent $event) {
    $data = $event->data;
    if ($data->selection == 'all') {
      $event->conditions[] = self::getName() . '::all';
    }
    elseif ($data->selection == 'list') {
      $event->conditions[] =  self::getName() . '::entity::' . $data->selected_entity->value . '::' . $data->selected_bundle->value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function filterEvents(EventFilterEvent $event) {
    /** @var \Drupal\Core\Entity\EntityBase $entity */
    $entity = $event->args['entity'];

    // If ifthenelserule entity is being loaded, don't return any flow since
    // returning anything results in infinite loop.
    if ($entity->getEntityTypeId() == 'ifthenelserule') {
      $event->query->condition('event', '');
      return;
    }

    $or = $event->query->orConditionGroup()
      ->condition('condition', self::getName() . '::all', 'CONTAINS')
      ->condition('condition', self::getName() . '::entity::' . $entity->getEntityTypeId() . '::' . $entity->bundle(), 'CONTAINS');

    $event->query->condition($or);
  }

}
