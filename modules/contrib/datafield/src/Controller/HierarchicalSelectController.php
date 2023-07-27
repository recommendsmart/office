<?php

namespace Drupal\datafield\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Hierarchical Select Controller.
 *
 * @package Drupal\datafield\Controller
 */
class HierarchicalSelectController extends ControllerBase {

  /**
   * Cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $cache;

  /**
   * Constructor Hierarchical Select.
   */
  public function __construct(CacheBackendInterface $staticCache) {
    $this->cache = $staticCache;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cache.default'),
    );
  }

  /**
   * Get taxonomies with parents.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request service.
   * @param string $vocabulary
   *   Taxonomy vocabulary.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Ajax to get select list.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function ajax(Request $request, string $vocabulary): JsonResponse {
    $data = [];
    $parent = $request->request->get('parent');
    $parents = explode('-', $parent);
    foreach ($parents as $parent) {
      $category = $this->getTaxonomy($vocabulary, $parent);
      if (!empty($category)) {
        $data[] = $category;
      }
    }
    return new JsonResponse([
      'data' => $data,
      'method' => 'GET',
      'status' => 200,
    ]);
  }

  /**
   * Get taxonomy term.
   *
   * @param string $vocabulary
   *   Taxonomy vocabulary.
   * @param int $parent
   *   Taxonomy term parent id.
   *
   * @return array
   *   Terms tree.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  private function getTaxonomy(string $vocabulary, int $parent = 0): array {
    $tree = [];
    $cid = $vocabulary . ':' . $parent;
    if ($cache = $this->cache->get($cid)) {
      $tree = $cache->data;
    }
    else {
      $terms = $this->entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadTree($vocabulary, $parent, 1, TRUE);
      foreach ($terms as $term) {
        $tree[$term->id()] = $term->getName();
      }
      $this->cache->set($cid, $tree);
    }
    return $tree;
  }

}
