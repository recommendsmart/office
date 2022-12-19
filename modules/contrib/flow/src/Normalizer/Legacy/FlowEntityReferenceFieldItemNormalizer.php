<?php

namespace Drupal\flow\Normalizer\Legacy;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Decorates the reference field item normalizer to support new entities.
 *
 * @internal This class is not meant for API usage and is subject to change.
 */
final class FlowEntityReferenceFieldItemNormalizer extends EntityReferenceFieldItemNormalizer {

  /**
   * Set this flag to TRUE to enable normalization of new entities.
   *
   * @var bool
   */
  public static bool $normalizeNewEntities = FALSE;

  /**
   * The decorating target.
   *
   * @var \Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer
   */
  protected EntityReferenceFieldItemNormalizer $decoratedNormalizer;

  /**
   * Set the decorating target.
   *
   * @param \Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer $normalizer
   *   The normalizer to decorate.
   */
  public function setDecoratedNormalizer(EntityReferenceFieldItemNormalizer $normalizer) {
    $this->decoratedNormalizer = $normalizer;
  }

  /**
   * Set the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $etm): void {
    $this->entityTypeManager = $etm;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($field_item, $format = NULL, array $context = []) {
    if (self::$normalizeNewEntities) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      if (($field_item instanceof EntityReferenceItem) && ($entity = $field_item->get('entity')->getValue())) {
        if ($entity->isNew()) {
          return [
            'entity' => $this->serializer->normalize($entity, get_class($entity)),
          ];
        }
      }
    }
    return $this->decoratedNormalizer->normalize($field_item, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (self::$normalizeNewEntities) {
      if (is_array($data) && isset($data['entity'], $context['target_instance'])) {
        $field_item = $context['target_instance'];
        if ($field_item instanceof EntityReferenceItem) {
          $target_type = $field_item->getFieldDefinition()->getSetting('target_type');
          $entity_class = $this->getEntityTypeManager()->getDefinition($target_type)->getClass();
          $field_item->setValue($this->serializer->denormalize($data['entity'], $entity_class));
          return $field_item;
        }
      }
    }
    return $this->decoratedNormalizer->denormalize($data, $class, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsNormalization($data, $format = NULL) {
    return $this->decoratedNormalizer->supportsNormalization($data, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    return $this->decoratedNormalizer->supportsDenormalization($data, $type, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function setSerializer(SerializerInterface $serializer) {
    parent::setSerializer($serializer);
    $this->decoratedNormalizer->setSerializer($serializer);
  }

}
