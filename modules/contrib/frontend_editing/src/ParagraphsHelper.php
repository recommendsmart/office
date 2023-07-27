<?php

namespace Drupal\frontend_editing;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs_edit\ParagraphLineageInspector;
use Drupal\paragraphs_edit\ParagraphLineageRevisioner;

/**
 * Class ParagraphsHelper contains methods to manage paragraphs crud operations.
 *
 * @package frontend_editing
 */
class ParagraphsHelper implements ParagraphsHelperInterface {

  /**
   * The lineage inspector.
   *
   * @var \Drupal\paragraphs_edit\ParagraphLineageInspector
   */
  protected $lineageInspector;

  /**
   * The lineage revisioner.
   *
   * @var \Drupal\paragraphs_edit\ParagraphLineageRevisioner
   */
  protected $lineageRevisioner;

  /**
   * ParagraphsHelper constructor.
   *
   * @param \Drupal\paragraphs_edit\ParagraphLineageInspector $paragraph_lineage_inspector
   *   The lineage inspector.
   * @param \Drupal\paragraphs_edit\ParagraphLineageRevisioner $paragraph_lineage_revisioner
   *   The lineage revisioner.
   */
  public function __construct(ParagraphLineageInspector $paragraph_lineage_inspector, ParagraphLineageRevisioner $paragraph_lineage_revisioner) {
    $this->lineageInspector = $paragraph_lineage_inspector;
    $this->lineageRevisioner = $paragraph_lineage_revisioner;
  }

  /**
   * {@inheritdoc}
   */
  public function allowUp(ParagraphInterface $paragraph) {
    return $this->allow($paragraph, 'up');
  }

  /**
   * {@inheritdoc}
   */
  public function allowDown(ParagraphInterface $paragraph) {
    return $this->allow($paragraph, 'down');
  }

  /**
   * Checks if the paragraph can be moved up or down.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to check.
   * @param string $operation
   *   The operation to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function allow(ParagraphInterface $paragraph, $operation) {
    // Check that the operation is valid.
    if (!in_array($operation, ['up', 'down'])) {
      return AccessResult::forbidden();
    }
    // Get paragraph parent entity.
    $parent = $paragraph->getParentEntity();
    // Check that the parent entity exists and the user has update access.
    if (!$parent || !$parent->access('update')) {
      return AccessResult::forbidden();
    }
    // Check that the parent entity has the paragraph field.
    $parent_field_name = $paragraph->get('parent_field_name')->value;
    if (!$parent->hasField($parent_field_name) || $parent->get($parent_field_name)->isEmpty()) {
      return AccessResult::forbidden();
    }
    // Get the paragraph items.
    $paragraph_items = $parent->get($parent_field_name)->getValue();
    if ($operation == 'up') {
      $item = reset($paragraph_items);
    }
    else {
      $item = end($paragraph_items);
    }
    if ($item['target_id'] == $paragraph->id()) {
      return AccessResult::forbidden();
    }
    else {
      return AccessResult::allowed();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function move(ParagraphInterface $paragraph, $operation) {
    $allow = $this->allow($paragraph, $operation);
    if (!$allow->isAllowed()) {
      return FALSE;
    }
    $parent = $paragraph->getParentEntity();
    $parent_field_name = $paragraph->get('parent_field_name')->value;
    $paragraph_items = $parent->get($parent_field_name)->getValue();
    if ($operation == 'up') {
      foreach ($paragraph_items as $delta => $paragraph_item) {
        if ($paragraph_item['target_id'] == $paragraph->id()) {
          if ($delta > 0) {
            $prev_paragraph = $paragraph_items[$delta - 1];
            $paragraph_items[$delta - 1] = $paragraph_items[$delta];
            $paragraph_items[$delta] = $prev_paragraph;
          }
          break;
        }
      }
    }
    else {
      $numitems = count($paragraph_items);
      foreach ($paragraph_items as $delta => $paragraph_item) {
        if ($paragraph_item['target_id'] == $paragraph->id()) {
          if ($delta < $numitems) {
            $next_paragraph = $paragraph_items[$delta + 1];
            $paragraph_items[$delta + 1] = $paragraph_items[$delta];
            $paragraph_items[$delta] = $next_paragraph;
          }
          break;
        }
      }
    }
    $parent->get($parent_field_name)->setValue($paragraph_items);
    $root_parent = $this->lineageInspector->getRootParent($paragraph);
    if ($this->lineageRevisioner->shouldCreateNewRevision($root_parent)) {
      $this->lineageRevisioner->saveNewRevision($parent);
    }
    else {
      $parent->save();
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectUrl(ParagraphInterface $paragraph) {
    $entity = $this->lineageInspector->getRootParent($paragraph);
    return $entity->hasLinkTemplate('canonical') ? $entity->toUrl() : Url::fromRoute('<front>');
  }

  /**
   * {@inheritdoc}
   */
  public function getParagraphRootParent(ParagraphInterface $paragraph) {
    return $this->lineageInspector->getRootParent($paragraph);
  }

}
