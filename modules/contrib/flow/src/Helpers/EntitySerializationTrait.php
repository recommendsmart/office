<?php

namespace Drupal\flow\Helpers;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\flow\Internals\NormalizerContainer;
use Symfony\Component\Serializer\Serializer;

/**
 * Trait for Flow-related components that serialize entities.
 */
trait EntitySerializationTrait {

  /**
   * The service name of the serializer.
   *
   * @var string
   */
  protected static $serializerServiceName = 'serializer';

  /**
   * The serializer.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  protected Serializer $serializer;

  /**
   * Returns a JSON-formatted string representation of the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to format as JSON.
   *
   * @return string
   *   The JSON string.
   */
  public function toJson(ContentEntityInterface $entity): string {
    return $this->getSerializer()->serialize($entity, 'json', ['json_encode_options' => JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES]);
  }

  /**
   * Converts the given JSON-formatted string to an entity object.
   *
   * @param string $json
   *   The JSON-formatted string.
   * @param string $entity_class
   *   The entity class to expect.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function fromJson(string $json, string $entity_class): ContentEntityInterface {
    return $this->getSerializer()->deserialize($json, $entity_class, 'json');
  }

  /**
   * Get the serializer.
   *
   * @return \Symfony\Component\Serializer\Serializer
   *   The serializer.
   */
  public function getSerializer(): Serializer {
    if (!isset($this->serializer)) {
      $this->serializer = \Drupal::service(self::$serializerServiceName);
    }
    return $this->serializer;
  }

  /**
   * Set the serializer.
   *
   * @param \Symfony\Component\Serializer\Serializer $serializer
   *   The serializer.
   */
  public function setSerializer(Serializer $serializer): void {
    $this->serializer = $serializer;
  }

  /**
   * Returns a normalized array of the given entity, suitable for configuration.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to normalize.
   *
   * @return array
   *   The normalized array.
   */
  public function toConfigArray(ContentEntityInterface $entity): array {
    $normalizer_container = NormalizerContainer::get();
    $normalizer_container->contentEntityNormalizer()::$cleanupFieldValues = TRUE;
    $normalizer_container->entityReferenceItemNormalizer()::$normalizeNewEntities = TRUE;
    try {
      return $this->getSerializer()->normalize($entity, get_class($entity));
    }
    finally {
      $normalizer_container->contentEntityNormalizer()::$cleanupFieldValues = FALSE;
      $normalizer_container->entityReferenceItemNormalizer()::$normalizeNewEntities = FALSE;
    }
  }

  /**
   * Converts the given normalized config array to an entity object.
   *
   * @param array $array
   *   The normalized array.
   * @param string $entity_class
   *   The entity class to expect.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface
   *   The entity.
   */
  public function fromConfigArray(array $array, string $entity_class): ContentEntityInterface {
    $normalizer_container = NormalizerContainer::get();
    $normalizer_container->contentEntityNormalizer()::$cleanupFieldValues = TRUE;
    $normalizer_container->entityReferenceItemNormalizer()::$normalizeNewEntities = TRUE;
    try {
      return $this->getSerializer()->denormalize($array, $entity_class);
    }
    finally {
      $normalizer_container->contentEntityNormalizer()::$cleanupFieldValues = FALSE;
      $normalizer_container->entityReferenceItemNormalizer()::$normalizeNewEntities = FALSE;
    }
  }

}
