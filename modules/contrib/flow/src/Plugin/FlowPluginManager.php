<?php

namespace Drupal\flow\Plugin;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Base class of flow plugin managers.
 */
class FlowPluginManager extends DefaultPluginManager {

  /**
   * The FlowPluginManager constructor.
   *
   * @param string $identifier
   *   The cache key and hook identifier.
   * @param string|bool $subdir
   *   The plugin's subdirectory, for example Plugin/views/filter.
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param string|null $plugin_interface
   *   (optional) The interface each plugin should implement.
   * @param string $plugin_definition_annotation_name
   *   (optional) The name of the annotation that contains the plugin definition.
   *   Defaults to 'Drupal\Component\Annotation\Plugin'.
   * @param string[] $additional_annotation_namespaces
   *   (optional) Additional namespaces to scan for annotation definitions.
   */
  public function __construct($identifier, $subdir, \Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, $plugin_interface = NULL, $plugin_definition_annotation_name = 'Drupal\Component\Annotation\Plugin', array $additional_annotation_namespaces = []) {
    parent::__construct($subdir, $namespaces, $module_handler, $plugin_interface, $plugin_definition_annotation_name, $additional_annotation_namespaces);
    $this->alterInfo($identifier);
    $this->setCacheBackend($cache_backend, $identifier,
      ['flow_plugins', 'config:flow_list']);
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    if (!$this->cacheTags) {
      throw new \RuntimeException("The Flow plugin manager has no cache tags defined.");
    }
    Cache::invalidateTags($this->cacheTags);
    $this->definitions = NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function setCachedDefinitions($definitions) {
    parent::setCachedDefinitions($definitions);

    // Also cache each single item for fine-granular cache loads.
    foreach ($definitions as $plugin_id => $definition) {
      $this->setCachedDefinition($plugin_id, $definition);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinition($plugin_id, $exception_on_invalid = TRUE) {
    if (!isset($this->definitions) || !isset($this->definitions[$plugin_id])) {
      if ($cached = $this->cacheGet($this->cacheKey . ':' . $plugin_id)) {
        return $this->doGetDefinition([$plugin_id => $cached->data], $plugin_id, $exception_on_invalid);
      }
    }

    // Fetch definitions if they're not loaded yet.
    if (!isset($this->definitions)) {
      $this->getDefinitions();
    }

    // Cache the single item if not yet done otherwise.
    if (isset($this->definitions[$plugin_id]) && !$this->cacheGet($this->cacheKey . ':' . $plugin_id)) {
      $this->setCachedDefinition($plugin_id, $this->definitions[$plugin_id]);
    }

    return $this->doGetDefinition($this->definitions, $plugin_id, $exception_on_invalid);
  }

  /**
   * Get a single cached definition of a plugin.
   *
   * @param string $plugin_id
   *   The plugin ID.
   *
   * @return array|null
   *   The cached definition, or NULL if no cached definition is available.
   */
  protected function getCachedDefinition($plugin_id) {
    if ($cached = $this->cacheGet($this->cacheKey . ':' . $plugin_id)) {
      return $cached->data;
    }

    return NULL;
  }

  /**
   * Get a single cached definition of a plugin.
   *
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $definition
   *   The plugin definition.
   */
  protected function setCachedDefinition($plugin_id, $definition) {
    $this->cacheSet($this->cacheKey . ':' . $plugin_id, $definition, $this->getCacheMaxAge(), $this->cacheTags);
  }

}
