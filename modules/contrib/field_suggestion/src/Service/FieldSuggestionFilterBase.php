<?php

namespace Drupal\field_suggestion\Service;

use Drupal\Core\DependencyInjection\ClassResolverInterface;

/**
 * Defines the filter service.
 */
abstract class FieldSuggestionFilterBase {

  /**
   * Holds an array of filter IDs, sorted by priority.
   *
   * @var string[]
   */
  protected $filters = [];

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Constructs a new FieldSuggestionFilterBase.
   *
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver.
   * @param string[] $filters
   *   An array of filter IDs.
   */
  public function __construct(
    ClassResolverInterface $class_resolver,
    array $filters
  ) {
    $this->filters = $filters;
    $this->classResolver = $class_resolver;
  }

}
