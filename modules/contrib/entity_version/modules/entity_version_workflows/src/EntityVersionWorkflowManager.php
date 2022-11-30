<?php

declare(strict_types = 1);

namespace Drupal\entity_version_workflows;

use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangesDetectionTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_version_workflows\Event\CheckEntityChangedEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Handler to control the entity version numbers for when workflows are used.
 */
class EntityVersionWorkflowManager {

  use EntityChangesDetectionTrait {
    getFieldsToSkipFromTranslationChangesCheck as getFieldsToSkipFromEntityChangesCheck;
  }

  /**
   * The symfony event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The moderation information service.
   *
   * @var \Drupal\content_moderation\ModerationInformationInterface
   */
  protected $moderationInfo;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntityVersionWorkflowHandler.
   *
   * @param \Drupal\content_moderation\ModerationInformationInterface $moderation_info
   *   The moderation information service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The symfony event dispatcher.
   */
  public function __construct(ModerationInformationInterface $moderation_info, EntityTypeManagerInterface $entityTypeManager, EventDispatcherInterface $eventDispatcher) {
    $this->moderationInfo = $moderation_info;
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
  }

  /**
   * Update the entity version field values of a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param string $field_name
   *   The name of the entity version field.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function updateEntityVersion(ContentEntityInterface $entity, $field_name): void {
    if ($entity->isNew()) {
      return;
    }

    // We don't update the entity version if it is flagged not to.
    if (isset($entity->entity_version_no_update) && $entity->entity_version_no_update) {
      return;
    }

    /** @var \Drupal\workflows\WorkflowInterface $workflow */
    $workflow = $this->moderationInfo->getWorkflowForEntity($entity);
    if (!$workflow) {
      return;
    }

    /** @var \Drupal\workflows\WorkflowTypeInterface $workflow_plugin */
    $workflow_plugin = $workflow->getTypePlugin();

    // Compute the transition being used in order to get the version actions
    // from its config. For this, we need to load the latest revision of the
    // entity. Unless the client specifies to use the current one instead.
    $use_current_revision = isset($entity->entity_version_use_current_revision) && $entity->entity_version_use_current_revision ?? FALSE;
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $revision_id = !$use_current_revision ? $storage->getLatestRevisionId($entity->id()) : $entity->getLoadedRevisionId();
    $revision = $storage->loadRevision($revision_id);

    // Retrieve the configured actions to perform for the version field numbers
    // from the transition.
    $current_state = $revision->moderation_state->value;
    $next_state = $entity->moderation_state->value;

    // Try to get the transition or do nothing.
    try {
      /** @var \Drupal\workflows\TransitionInterface $transition */
      $transition = $workflow_plugin->getTransitionFromStateToState($current_state, $next_state);
    }
    catch (\InvalidArgumentException $e) {
      return;
    }

    $config_values = $workflow->getThirdPartySetting('entity_version_workflows', $transition->id());
    if (!$config_values) {
      return;
    }

    // If the config is defined to check entity field values changes we don't
    // act if they did not change.
    $check_values_changed = !empty($config_values['check_values_changed']);
    if ($check_values_changed && !$this->isEntityChanged($entity)) {
      return;
    }

    // Remove this to leave the version settings only for the iteration.
    if (isset($config_values['check_values_changed'])) {
      unset($config_values['check_values_changed']);
    }

    // Execute all the configured actions on all the values of the field.
    foreach ($config_values as $version => $action) {
      foreach ($entity->get($field_name)->getValue() as $delta => $value) {
        $entity->get($field_name)->get($delta)->$action($version);
      }
    }
  }

  /**
   * Check if the entity has changed.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity object.
   *
   * @return bool
   *   Return true if the entity has changed, otherwise return false.
   */
  protected function isEntityChanged(ContentEntityInterface $entity): bool {
    $fields = array_keys($entity->toArray());

    // Some of the fields we should not check if there are changes on. This is
    // because they are irrelevant or that they are computed.
    $field_blacklist = $this->getFieldsToSkipFromEntityChangesCheck($entity);
    $event = new CheckEntityChangedEvent();
    $event->setFieldBlacklist($field_blacklist);
    $this->eventDispatcher->dispatch(CheckEntityChangedEvent::EVENT, $event);
    $field_blacklist = $event->getFieldBlacklist();

    // We consider the latest revision as original to compare with the entity.
    /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
    $latest_revision_id = $storage->getLatestRevisionId($entity->id());
    $latestRevision = $storage->loadRevision($latest_revision_id);

    // Remove the blacklisted fields from checking.
    $fields = array_diff($fields, $field_blacklist);
    foreach ($fields as $field) {
      // If we encounter a change, we directly return.
      if ($entity->get($field)->hasAffectingChanges($latestRevision->get($field)->filterEmptyItems(), $entity->language()->getId())) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
