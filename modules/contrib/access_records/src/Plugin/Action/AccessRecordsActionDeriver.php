<?php

namespace Drupal\access_records\Plugin\Action;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The deriver for access record actions.
 */
class AccessRecordsActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
   protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return (new static())
      ->setEntityTypeManager($container->get('entity_type.manager'))
      ->setEntityFieldManager($container->get('entity_field.manager'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\access_records\AccessRecordTypeInterface $ar_type */
    foreach ($this->entityTypeManager->getStorage('access_record_type')->loadMultiple() as $ar_type) {
      if (!$ar_type->status()) {
        continue;
      }

      $subject_field_names = $ar_type->getSubjectFieldNames();
      $target_field_names = $ar_type->getTargetFieldNames();
      $subject_type = $this->entityTypeManager->getDefinition($ar_type->getSubjectTypeId());
      $target_type = $this->entityTypeManager->getDefinition($ar_type->getTargetTypeId());

      if (!in_array($subject_type->getKey('id'), $subject_field_names, TRUE) || !in_array($target_type->getKey('id'), $target_field_names, TRUE)) {
        continue;
      }

      $args = [
        '@ar_type' => $ar_type->label(),
        '@subject' => $subject_type->getLabel(),
        '@target' => $target_type->getLabel(),
      ];

      $label = $ar_type->label() instanceof TranslatableMarkup ? $ar_type->label()->getUntranslatedString() : $ar_type->label();
      if (mb_stripos($label, 'access') !== FALSE) {
        // Remove redundant "access" within the action label.
        $base_plugin_definition['label'] = str_replace(' access', '', $base_plugin_definition['label']);
      }

      $this->derivatives[$ar_type->id()] = [
        'label' => new TranslatableMarkup($base_plugin_definition['label'], $args),
        'description' => new TranslatableMarkup($base_plugin_definition['description'], $args),
        'type' => $target_type->id(),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

  /**
   * Set the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $etm): AccessRecordsActionDeriver {
    $this->entityTypeManager = $etm;
    return $this;
  }

  /**
   * Set the entity field manager.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $efm
   *   The entity field manager.
   *
   * @return $this
   */
  public function setEntityFieldManager(EntityFieldManagerInterface $efm): AccessRecordsActionDeriver {
    $this->entityFieldManager = $efm;
    return $this;
  }

}
