<?php

namespace Drupal\widget_type;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of widget registry sources.
 */
class WidgetRegistrySourceListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['endpoint'] = $this->t('Endpoint');
    $header['status'] = $this->t('Status');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\widget_type\WidgetRegistrySourceInterface $entity */
    [$color, $complementary_color] = $entity->calculateColors();
    $row['label'] = [
      'data' => [
        '#type' => 'html_tag',
        '#tag' => 'span',
        '#value' => $entity->label(),
        '#attributes' => [
          'style' => 'color: #' . $color . '; background-color: #' . $complementary_color,
        ],
        '#attached' => ['library' => ['widget_type/widget_type.admin']],
      ],
      'class' => ['source']
    ];
    $row['endpoint'] = $entity->get('endpoint');
    $row['status'] = $entity->status() ? $this->t('Enabled') : $this->t('Disabled');
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build['table'] = [
      '#type' => 'details',
      '#title' => $this->t('Widget Registry Sources'),
      '#description' => $this->t('Widget registry sources declare all the JSON documents Drupal will process in order to ingest widget definitions.'),
      '#open' => TRUE,
      0 => parent::render(),
    ];

    $total = $this->getStorage()
      ->getQuery()
      ->accessCheck(TRUE)
      ->condition('status', TRUE)
      ->count()
      ->execute();

    $build['actions'] = [
      '#type' => 'actions',
      'summary' => [
        '#markup' => $this->t('Total enabled registries: @total', ['@total' => $total]),
      ],
    ];
    return $build;
  }

}
