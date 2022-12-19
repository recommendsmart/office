<?php

namespace Drupal\node_singles\Service;

use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * An interface for the node singles service.
 */
interface NodeSinglesInterface {

  /**
   * Checks whether a single node exists for this node type.
   *
   * If missing, it will create one.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   The node type.
   */
  public function checkSingle(NodeTypeInterface $type): void;

  /**
   * Returns a loaded single node by node type.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The single node or NULL if it cannot be created.
   */
  public function getSingle(NodeTypeInterface $type, ?string $langcode = NULL): ?NodeInterface;

  /**
   * Returns a loaded single node by node type ID.
   *
   * @param string $bundle
   *   The node type ID.
   * @param string|null $langcode
   *   The language code. Defaults to the current content language.
   */
  public function getSingleByBundle(string $bundle, ?string $langcode = NULL): ?NodeInterface;

  /**
   * Returns a loaded single node by its bundle class name.
   *
   * @param class-string<T> $className
   *   The bundle class name of the single.
   * @param string|null $langcode
   *   The language code of the single.
   *
   * @template T of \Drupal\node\NodeInterface
   *
   * @return T|null
   *   The single node.
   */
  public function getSingleByClass(string $className, ?string $langcode = NULL): ?NodeInterface;

  /**
   * Check whether a node type is single or not.
   *
   * @param \Drupal\node\NodeTypeInterface $type
   *   The node type to check.
   *
   * @return bool
   *   TRUE if the node type is single, FALSE otherwise.
   */
  public function isSingle(NodeTypeInterface $type): bool;

  /**
   * Get all single content types.
   *
   * @return \Drupal\node\NodeTypeInterface[]
   *   An array of single node types.
   */
  public function getAllSingles(): array;

}
