<?php

namespace Drupal\flow\Normalizer\Legacy;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\serialization\Normalizer\ContentEntityNormalizer;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Decorates the content entity normalizer for cleaning up field values.
 *
 * The cleanup removes fields that differ on records, such as the ID and
 * revision ID. This cleanup is meant for storing field values into
 * configuration.
 *
 * @internal This class is not meant for API usage and is subject to change.
 */
final class FlowContentEntityNormalizer extends ContentEntityNormalizer {

  /**
   * Set this flag to TRUE for enabling a cleanup of normalized field values.
   *
   * @var bool
   */
  public static bool $cleanupFieldValues = FALSE;

  /**
   * The decorating target.
   *
   * @var \Drupal\serialization\Normalizer\EntityReferenceFieldItemNormalizer
   */
  protected ContentEntityNormalizer $decoratedNormalizer;

  /**
   * Set the decorating target.
   *
   * @param \Drupal\serialization\Normalizer\ContentEntityNormalizer $normalizer
   *   The normalizer to decorate.
   */
  public function setDecoratedNormalizer(ContentEntityNormalizer $normalizer) {
    $this->decoratedNormalizer = $normalizer;
  }

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    if (self::$cleanupFieldValues && ($entity instanceof ContentEntityInterface)) {
      foreach ($entity as $item_list) {
        $item_list->filterEmptyItems();
      }
      $values = $this->decoratedNormalizer->normalize($entity, $format, $context);
      $this->cleanupFieldValues($entity, $values);
      return $values;
    }
    return $this->decoratedNormalizer->normalize($entity, $format, $context);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
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

  /**
   * Cleans up field values, specifically for configured content within Flow.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity.
   * @param array &$values
   *   The field values.
   */
  protected function cleanupFieldValues(ContentEntityInterface $entity, array &$values) {
    foreach ($values as $field_name => &$field_values) {
      $is_reference_field = FALSE;
      $target_is_content = FALSE;
      $item_list = $entity->get($field_name);
      if ($item_list instanceof EntityReferenceFieldItemListInterface) {
        $is_reference_field = TRUE;
        $item_definition = $item_list->getFieldDefinition()->getFieldStorageDefinition();
        $target_entity_type = $this->getEntityTypeManager()->getDefinition($item_definition->getSetting('target_type'));
        $target_is_content = $target_entity_type->entityClassImplements(ContentEntityInterface::class);
      }

      foreach ($field_values as &$field_value) {
        if ($is_reference_field) {
          // No usage for the url info.
          unset($field_value['url']);

          // Special treatment for files to be stored permanently.
          /** @var \Drupal\file\FileInterface $file */
          if (isset($field_value['target_type'], $field_value['target_id']) && ($field_value['target_type'] === 'file') && ($file = $this->getEntityTypeManager()->getStorage('file')->load($field_value['target_id']))) {
            if ($file->isTemporary()) {
              $file->setPermanent();
              $file->save();
            }
          }
        }
        // For content entity references, rely on the UUID. For any other
        // entity (that is always a config entity at this time) rely on the ID.
        // One exception is made for users that have ID 0 (anonymous) and
        // ID 1 (admin).
        if ($target_is_content) {
          if ($target_entity_type->id() === 'user' && isset($field_value['target_id']) && in_array($field_value['target_id'], [0, 1])) {
            unset($field_value['target_uuid']);
          }
          elseif (!empty($field_value['target_uuid'])) {
            unset($field_value['target_id']);
          }
        }
        else {
          unset($field_value['target_uuid']);
        }

        // @todo Remove this workaround once #2972988 is fixed.
        unset($field_value['processed']);

      }
    }

    $entity_type = $entity->getEntityType();
    // Remove the UUID as it won't be used at all for configuration, and do a
    // little cleanup by filtering out empty values.
    $uuid_key = $entity_type->hasKey('uuid') ? $entity_type->getKey('uuid') : 'uuid';
    unset($values[$uuid_key]);
    // Remove the created and changed timestamp, as it does not make sense to
    // store as configuration.
    unset($values['created'], $values['changed']);
    $entity_keys = $entity_type->getKeys();
    // Remove IDs and timestamps.
    foreach (array_intersect_key($entity_keys, array_flip(['id', 'revision', 'created', 'changed'])) as $k_1) {
      unset($values[$k_1]);
    }
    foreach ($values as $k_1 => $v_1) {
      if (!in_array($k_1, $entity_keys) && !is_scalar($v_1) && empty($v_1)) {
        unset($values[$k_1]);
      }
      elseif (is_iterable($v_1)) {
        $is_empty = TRUE;
        foreach ($v_1 as $v_2) {
          if (!empty($v_2) || (!is_null($v_2) && $v_2 !== '' && $v_2 !== 0 && $v_2 !== '0')) {
            $is_empty = FALSE;
            break;
          }
        }
        if ($is_empty) {
          unset($values[$k_1]);
        }
      }
    }
  }

}
