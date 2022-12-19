<?php

namespace Drupal\node_singles\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;

/**
 * The node singles service.
 */
class NodeSingles implements NodeSinglesInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The settings config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * Constructs the service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    StateInterface $state,
    LanguageManagerInterface $language_manager,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->languageManager = $language_manager;
    $this->config = $config_factory->get('node_singles.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function checkSingle(NodeTypeInterface $type): void {
    if (!$this->isSingle($type)) {
      return;
    }

    $entity = NULL;
    $storage = $this->entityTypeManager->getStorage('node');
    $nodes = $storage->getQuery()
      ->condition('type', $type->id())
      ->execute();

    // There are multiple nodes, this shouldn't happen.
    if (count($nodes) > 1) {
      throw new \Exception('Single Bundle with more then one entity.');
    }

    // There aren't any nodes yet, so create one.
    if (empty($nodes)) {
      $entity = $this->createNode($type);
    }

    // There's 1 node, but no snowflake (or a snowflake that doesn't
    // match the nid)
    if (count($nodes) === 1) {
      $snowFlake = $this->getSnowFlake($type);
      $node = reset($nodes);

      if ($node !== $snowFlake) {
        $entity = $storage->load($node);
      }
    }

    if ($entity instanceof NodeInterface) {
      $this->setSnowFlake($type, $entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSingle(NodeTypeInterface $type, ?string $langcode = NULL): ?NodeInterface {
    $langcode = $langcode ?? $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    $tries = 0;

    do {
      $tries++;
      $id = $this->getSnowFlake($type);

      if (!$id) {
        $this->checkSingle($type);
      }

      $node = $this->loadNode($id, $langcode);

      if (!$node instanceof NodeInterface) {
        $this->checkSingle($type);
      }
    } while ($tries < 2);

    return $node;
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleByBundle(string $bundle, ?string $langcode = NULL): ?NodeInterface {
    $types = $this->getAllSingles();

    return isset($types[$bundle])
      ? $this->getSingle($types[$bundle], $langcode)
      : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSingleByClass(string $className, ?string $langcode = NULL): ?NodeInterface {
    $storage = $this->entityTypeManager
      ->getStorage('node');

    if (!method_exists($storage, 'getBundleFromClass')) {
      throw new \LogicException('Entity bundle classes were introduced in Drupal 9.3.0-alpha1. Please update to be able to use this method.');
    }

    $bundle = $storage->getBundleFromClass($className);

    if ($bundle === NULL) {
      return NULL;
    }

    return $this->getSingleByBundle($bundle);
  }

  /**
   * {@inheritdoc}
   */
  public function isSingle(NodeTypeInterface $type): bool {
    return $type->getThirdPartySetting('node_singles', 'is_single', FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getAllSingles(): array {
    $list = &drupal_static(__FUNCTION__);

    if (isset($list)) {
      return $list;
    }

    $list = [];
    /** @var \Drupal\node\NodeTypeInterface $type */
    $nodeTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    foreach ($nodeTypes as $type) {
      if ($this->isSingle($type)) {
        $list[$type->get('type')] = $type;
      }
    }

    return $list;
  }

  /**
   * Store the association between a single node and its node type.
   */
  protected function setSnowFlake(NodeTypeInterface $type, NodeInterface $node): void {
    $this->state->set($this->getSnowFlakeKey($type), (int) $node->id());
  }

  /**
   * Get the associated single node for a node type.
   */
  protected function getSnowFlake(NodeTypeInterface $type): ?int {
    return $this->state->get($this->getSnowFlakeKey($type));
  }

  /**
   * Get the key under which the associated single node is stored.
   */
  protected function getSnowFlakeKey(NodeTypeInterface $type): string {
    return 'node_singles.' . $type->id();
  }

  /**
   * Create a single node for a node type.
   */
  protected function createNode(NodeTypeInterface $type): NodeInterface {
    /** @var \Drupal\node\NodeInterface $entity */
    $entity = $this->entityTypeManager
      ->getStorage('node')
      ->create([
        'type' => $type->id(),
        'title' => $type->label(),
        'path' => ['alias' => '/' . str_replace('_', '-', $type->id())],
      ]);
    $entity->save();

    return $entity;
  }

  /**
   * Load a single node, given an id and langcode.
   */
  protected function loadNode(string $id, string $langcode): ?NodeInterface {
    $single = $this->entityTypeManager->getStorage('node')->load($id);

    if (!$single instanceof NodeInterface) {
      return NULL;
    }

    if ($single->hasTranslation($langcode)) {
      return $single->getTranslation($langcode);
    }

    if ($single->get('langcode')->value === $langcode || !$this->config->get('strict_translation')) {
      return $single;
    }

    return NULL;
  }

}
