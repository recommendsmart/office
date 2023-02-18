<?php

namespace Drupal\paragraphs_table\Form;

use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Provides a form for deleting a paragraph from a node.
 */
class ParagraphDeleteForm extends ContentEntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  private function getInfoItem() {
    $field_name = $this->entity->get('parent_field_name')->value;
    $host = $this->entity->getParentEntity();
    $entity_type = $host->getEntityTypeId();
    $bundle = $host->bundle();
    $entityFieldManager = \Drupal::service('entity_field.manager')
      ->getFieldDefinitions($entity_type, $bundle);
    return [
      '%type' => $entityFieldManager[$field_name]->getLabel(),
      '%id' => $this->entity->id(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %type item %id?', $this->getInfoItem());
  }

  /**
   * {@inheritdoc}
   */
  protected function getDeletionMessage() {
    return $this->t('Paragraph %type item %id has been deleted.', $this->getInfoItem());
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $parent = $this->entity->getParentEntity();
    $parent->get($this->entity->get('parent_field_name')->value);
    $parent_field = $this->getParentField($this->entity);
    $parent_field_item = $this->findParentFieldItem($this->entity, $parent_field);
    if ($parent_field_item) {
      $parent_field->removeItem($parent_field_item->getName());
    }
    // Directly delete paragraphs, it does not make new revision.
    $this->entity->delete();

    if ($this->shouldCreateNewRevision($parent)) {
      $this->saveNewRevision($parent);
    }
    else {
      $parent->save();
    }
  }

  /**
   * Gets the field the paragraph is referenced from.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph data.
   *
   * @return \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList|null
   *   Parent field.
   */
  public function getParentField(ParagraphInterface $paragraph) {
    $parent = $paragraph->getParentEntity();
    if (!$parent) {
      return NULL;
    }
    return $parent->get($paragraph->get('parent_field_name')->value);
  }

  /**
   * Finds the field item the paragraph is referenced from.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   Paragraph data.
   * @param \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList $field
   *   Field item.
   *
   * @return \Drupal\entity_reference_revisions\Plugin\Field\FieldType\EntityReferenceRevisionsItem|null
   *   Referenced field item.
   */
  protected function findParentFieldItem(ParagraphInterface $paragraph, EntityReferenceRevisionsFieldItemList $field) {
    $paragraph_id = $paragraph->id();
    $paragraph_revision_id = $paragraph->getRevisionId();

    foreach ($field as $item) {
      if ($item->target_id == $paragraph_id && $item->target_revision_id == $paragraph_revision_id) {
        return $item;
      }
    }
    return NULL;
  }

  /**
   * Checks if a given entity should be saved as a new revision by default.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check for default new revision.
   *
   * @return bool
   *   TRUE if this entity is set to be saved as a new revision by default,
   *   FALSE otherwise.
   */
  protected function shouldCreateNewRevision(EntityInterface $entity) {
    $new_revision_default = FALSE;

    if ($bundle_entity_type = $entity->getEntityType()->getBundleEntityType()) {
      $bundle_entity = $this->entityTypeManager
        ->getStorage($bundle_entity_type)
        ->load($entity->bundle());

      if ($bundle_entity instanceof RevisionableEntityBundleInterface) {
        $new_revision_default = $bundle_entity->shouldCreateNewRevision();
      }
    }

    return $new_revision_default;
  }

  /**
   * Saves all of given entity's lineage as new revisions.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity whose lineage to save as new revisions.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  public function saveNewRevision(ContentEntityInterface $entity) {
    $result = $this->doSaveNewRevision($entity);

    while ($entity instanceof ParagraphInterface) {
      $parent = $entity->getParentEntity();
      if (!$parent) {
        break;
      }

      $this->doSaveNewRevision($parent);

      $entity = $parent;
    }

    return $result;
  }

  /**
   * Saves an entity as a new revision.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to save.
   *
   * @return int
   *   Either SAVED_NEW or SAVED_UPDATED, depending on the operation performed.
   */
  protected function doSaveNewRevision(ContentEntityInterface $entity) {
    // Get the parent field item before saving, as after saving the
    // revision ID will be changed.
    if ($entity instanceof ParagraphInterface) {
      $parent_field_item = $this->getParentFieldItem($entity);
    }

    try {
      $entity->setNewRevision();
    }
    catch (\LogicException $e) {
      // A content entity not necessarily supports revisioning.
    }

    $status = $entity->save();

    if (isset($parent_field_item)) {
      $parent_field_item->set('target_revision_id', $entity->getRevisionId());
    }

    return $status;
  }

}
