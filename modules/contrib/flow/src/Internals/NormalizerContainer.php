<?php

namespace Drupal\flow\Internals;

/**
 * Contains serialization normalizers provided by the Flow module.
 *
 * Normalizers are private services. However, flags need to be set from
 * elsewhere on these normalizers. To get them, this class exists as a wrapper.
 *
 * @internal This class is not meant for API usage and is subject to change.
 */
final class NormalizerContainer {

  /**
   * The content entity normalizer.
   *
   * @var mixed
   */
  private $contentEntityNormalizer;

  /**
   * The entity reference item normalizer.
   *
   * @var mixed
   */
  private $entityReferenceItemNormalizer;

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\flow\Internals\NormalizerContainer
   *   The service instance.
   */
  public static function get(): NormalizerContainer {
    return \Drupal::service('flow.normalizer_container');
  }

  /**
   * The NormalizerContainer constructor.
   *
   * @param mixed $content_entity_normalizer
   *   The content entity normalizer.
   * @param mixed $entity_reference_item_normalizer
   *   The entity reference item normalizer.
   */
  public function __construct($content_entity_normalizer, $entity_reference_item_normalizer) {
    $this->contentEntityNormalizer = $content_entity_normalizer;
    $this->entityReferenceItemNormalizer = $entity_reference_item_normalizer;
  }

  /**
   * Get the content entity normalizer.
   *
   * @return mixed
   *   The content entity normalizer.
   */
  public function contentEntityNormalizer() {
    return $this->contentEntityNormalizer;
  }

  /**
   * Get the entity reference item normalizer.
   *
   * @return mixed
   *   The entity reference item normalizer.
   */
  public function entityReferenceItemNormalizer() {
    return $this->entityReferenceItemNormalizer;
  }

}
