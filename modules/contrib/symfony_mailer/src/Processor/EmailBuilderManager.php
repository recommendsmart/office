<?php

namespace Drupal\symfony_mailer\Processor;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides the email builder plugin manager.
 */
class EmailBuilderManager extends DefaultPluginManager implements EmailBuilderManagerInterface {

  use StringTranslationTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The key value storage.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $keyValue;

  /**
   * Mapping from state code to human-readable string.
   *
   * @var string[]
   */
  protected $stateName;

  /**
   * Constructs the EmailBuilderManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $key_value_factory
   *   The key value store.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, KeyValueFactoryInterface $key_value_factory) {
    parent::__construct('Plugin/EmailBuilder', $namespaces, $module_handler, 'Drupal\symfony_mailer\Processor\EmailBuilderInterface', 'Drupal\symfony_mailer\Annotation\EmailBuilder');
    $this->entityTypeManager = $entity_type_manager;
    $this->keyValue = $key_value_factory->get('mailer');
    $this->setCacheBackend($cache_backend, 'symfony_mailer_builder_plugins');
    $this->alterInfo('mailer_builder_info');

    $this->stateName = [
      self::IMPORT_READY => $this->t('Ready'),
      self::IMPORT_COMPLETE => $this->t('Complete'),
      self::IMPORT_SKIPPED => $this->t('Skipped'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function processDefinition(&$definition, $plugin_id) {
    $parts = explode('.', $plugin_id);
    $type = $definition['type'] = array_shift($parts);
    if ($parts) {
      $definition['sub_type'] = array_shift($parts);
    }

    // Look up the related entity or module, which can be used to generate the
    // label and provider.
    if ($definition['has_entity']) {
      if ($entity_type = $this->entityTypeManager->getDefinition($type, FALSE)) {
        $default_label = $entity_type->getLabel();
        $proxy_provider = $entity_type->getProvider();
      }
    }
    elseif ($this->moduleHandler->moduleExists($type)) {
      $default_label = $this->moduleHandler->getName($type);
      $proxy_provider = $type;
    }

    if ($definition['proxy']) {
      // Default the provider, or fallback to a dummy provider that will cause
      // the definition to be removed if the related module is not installed.
      // @see DefaultPluginManager::findDefinitions()
      $definition['provider'] = $proxy_provider ?? '_';
    }

    if (isset($default_label) && !$definition['label']) {
      // Default the label.
      $definition['label'] = $default_label;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getImportInfo() {
    $state_all = $this->keyValue->get('import', []);

    foreach ($this->getDefinitions() as $id => $definition) {
      if ($definition['import']) {
        $state = $state_all[$id] ?? self::IMPORT_READY;

        $info[$id] = [
          'name' => "$definition[import] ($id)",
          'state' => $state,
          'state_name' => $this->stateName[$state],
          'warning' => $definition['import_warning'] ?? NULL,
        ];
      }
    }

    return $info ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function importRequired() {
    $state_all = $this->keyValue->get('import', []);

    foreach ($this->getDefinitions() as $id => $definition) {
      if ($definition['import'] && (($state_all[$id] ?? self::IMPORT_READY) == self::IMPORT_READY)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function import(string $id) {
    $this->createInstance($id)->import();
    $this->setImportState($id, self::IMPORT_COMPLETE);
  }

  /**
   * {@inheritdoc}
   */
  public function importAll() {
    foreach ($this->getImportInfo() as $id => $info) {
      if ($info['state'] == self::IMPORT_READY) {
        $this->import($id);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setImportState(string $id, int $state) {
    $state_all = $this->keyValue->get('import');
    $state_all[$id] = $state;
    $this->keyValue->set('import', $state_all);
  }

}
