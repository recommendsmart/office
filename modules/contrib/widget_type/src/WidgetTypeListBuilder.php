<?php

namespace Drupal\widget_type;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Provides a list controller for the widget type entity type.
 */
class WidgetTypeListBuilder extends EntityListBuilder {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The redirect destination service.
   *
   * @var \Drupal\Core\Routing\RedirectDestinationInterface
   */
  protected $redirectDestination;

  /**
   * Constructs a new WidgetTypeListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Routing\RedirectDestinationInterface $redirect_destination
   *   The redirect destination service.
   */
  public function __construct(
    EntityTypeInterface $entity_type,
    EntityStorageInterface $storage,
    DateFormatterInterface $date_formatter,
    RedirectDestinationInterface $redirect_destination
  ) {
    parent::__construct($entity_type, $storage);
    $this->dateFormatter = $date_formatter;
    $this->redirectDestination = $redirect_destination;
    $this->limit = NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('date.formatter'),
      $container->get('redirect.destination')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function load() {
    $entities = parent::load();
    // Sort by Registry Source (aka "Provider") first, keeping "no provider"
    // widgets on the bottom. Then sort by widget type name.
    uasort($entities, static function (WidgetTypeInterface $a, WidgetTypeInterface $b): int {
      $provider_a = $a->widget_registry_source->entity ?? NULL;
      $provider_b = $b->widget_registry_source->entity ?? NULL;
      // Type A has registry source, Type B does not.
      if (isset($provider_a) && !isset($provider_b)) {
        return -1;
      }
      // Type B has registry source, Type A does not.
      if (!isset($provider_a) && isset($provider_b)) {
        return 1;
      }
      // First compare by registry source (aka Provider) labels.
      if (isset($provider_a, $provider_b)) {
        $provider_comparison = strcasecmp($provider_a->label(), $provider_b->label());
        if ($provider_comparison !== 0) {
          return $provider_comparison;
        }
      }
      // When providers match, compare by Widget Type name.
      return strcasecmp($a->getName(), $b->getName());
    });
    return $entities;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $title = $this->t('Filter widget types by name, source, directory, or version.');
    $build['table'] = [
      '#type' => 'details',
      '#title' => $this->t('Widget Types'),
      '#description' => $this->t(
        'Widget types are widget definitions. They represent JS applications ready to be configured and embedded in Drupal.'
      ),
      '#open' => TRUE,
      'search' => [
        '#type' => 'search',
        '#title' => $this->t('Filter'),
        '#placeholder' => $title,
        '#attributes' => [
          'class' => ['input-filter-text'],
          'data-table' => 'table#edit-data',
          'autocomplete' => 'off',
          'title' => $title,
        ],
        '#attached' => [
          'library' => ['widget_type/widget_type.admin'],
        ],
      ],
      'data' => parent::render(),
    ];
    try {
      $build['table']['data']['table']['#id'] = 'edit-data';
      $title = $this->t('Ingest some!');
      $link = Link::createFromRoute($title, 'widget_ingestion.manual', [], [
        'attributes' => ['class' => ['button', 'button--primary']],
      ]);
      $build['table']['data']['table']['#empty'] = $this->t(
        'There are no widget types yet. @link',
        ['@link' => $link->toString()]
      );
    } catch (RouteNotFoundException $exception) {
      // Intentionally left blank.
    }

    $total = $this->getStorage()
      ->getQuery()
      ->accessCheck(TRUE)
      ->count()
      ->execute();

    $build['actions'] = [
      '#type' => 'actions',
      'summary' => [
        '#markup' => $this->t('Total widget types: @total', ['@total' => $total]),
      ],
    ];
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header = [
      'status' => NULL,
      'title' => $this->t('Title'),
      'changed' => $this->t('Updated'),
      'source' => $this->t('Source'),
      'directory' => $this->t('Directory'),
      'remote_status' => $this->t('Remote status'),
      'version' => $this->t('Version'),
    ];
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    assert($entity instanceof WidgetTypeInterface);
    $status_text = $entity->isEnabled() ? $this->t('Enabled') : $this->t('Disabled');
    $source = $entity->widget_registry_source->entity;
    assert($source instanceof WidgetRegistrySourceInterface);
    $source_label = $source ? $source->label() : $this->t('- Not available -');
    [$color, $complementary_color] = $source ? $source->calculateColors() : ['000000', 'FFFFFF'];
    $row = [
      'status' => [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $status_text,
        ],
        'class' => ['entity-status', 'entity-status-' . ($entity->isEnabled() ? 'on' : 'off')],
        'title' => $status_text,
      ],
      'title' => [
        'data' => [
          '#type' => 'link',
          '#title' => $entity->getName(),
          '#url' => $entity->toUrl(),
        ],
        'class' => ['entity-label'],
      ],
      'changed' => $this->dateFormatter->format($entity->getChangedTime(), 'html_date'),
      'source' => [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $source_label,
          '#attributes' => [
            'style' => 'color: #' . $color . '; background-color: #' . $complementary_color,
          ],
        ],
        'class' => ['source'],
      ],
      'directory' => [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $entity->getDirectory(),
          '#attributes' => ['title' => $entity->getDirectory()],
        ],
        'class' => ['directory-url'],
      ],
      'remote_status' => [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $entity->getRemoteStatus(),
        ],
        'class' => ['remote-status'],
      ],
      'version' => [
        'data' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $entity->getVersion(),
        ],
        'class' => ['version'],
      ],
    ];
    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultOperations(EntityInterface $entity) {
    $operations = parent::getDefaultOperations($entity);
    $destination = $this->redirectDestination->getAsArray();
    foreach (array_keys($operations) as $key) {
      $operations[$key]['query'] = $destination;
    }
    return $operations;
  }

}
