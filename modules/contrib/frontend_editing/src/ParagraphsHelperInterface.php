<?php

namespace Drupal\frontend_editing;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Defines ParagraphsHelperInterface Interface.
 *
 * @package src
 */
interface ParagraphsHelperInterface {

  /**
   * Checks if the paragraph can be moved up.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function allowUp(ParagraphInterface $paragraph);

  /**
   * Checks if the paragraph can be moved down.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to check.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function allowDown(ParagraphInterface $paragraph);

  /**
   * Moves the paragraph up or down.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to move.
   * @param string $operation
   *   The operation to perform.
   *
   * @return bool
   *   TRUE if the paragraph was moved, FALSE otherwise.
   */
  public function move(ParagraphInterface $paragraph, $operation);

  /**
   * Get the redirect url.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   *
   * @return \Drupal\Core\Url
   *   The redirect url.
   */
  public function getRedirectUrl(ParagraphInterface $paragraph);

  /**
   * Get the root parent of a paragraph.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The root parent.
   */
  public function getParagraphRootParent(ParagraphInterface $paragraph);

}
