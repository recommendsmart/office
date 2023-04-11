<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_version_history\Kernel;

use Drupal\entity_test\Entity\EntityTestBundle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Route;

/**
 * Tests the entity version history local task.
 *
 * @group entity_version_history
 */
class HistoryTabTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'text',
    'system',
    'entity_test',
    'entity_version',
    'entity_version_history',
    'entity_version_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('entity_version_settings');
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('entity_test_with_bundle');
    $this->installEntitySchema('entity_test_bundle');

    $this->installConfig([
      'user',
      'node',
      'system',
      'field',
      'entity_version',
      'entity_version_history',
      'entity_version_test',
    ]);

    EntityTestBundle::create([
      'id' => 'entity_test_bundle',
    ])->save();

    $this->container->get('entity_version.entity_version_installer')->install('entity_test_with_bundle', ['entity_test_bundle']);

    // Create a history tab setting for the corresponding entity type
    // and bundle.
    $history_storage = $this->container->get('entity_type.manager')->getStorage('entity_version_settings');
    $history_storage->create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'first_bundle',
      'target_field' => 'field_entity_version',
    ])->save();
    // Create a configuration for the entity type and bundle which still
    // not supposed to have a history tab route.
    $history_storage = $this->container->get('entity_type.manager')->getStorage('entity_version_settings');
    $history_storage->create([
      'target_entity_type_id' => 'entity_test_with_bundle',
      'target_bundle' => 'entity_test_bundle',
      'target_field' => 'version',
    ])->save();
  }

  /**
   * Tests the History routes.
   */
  public function testHistoryRoutes(): void {
    $route_provider = $this->container->get('router.route_provider');
    $local_task_manager = $this->container->get('plugin.manager.menu.local_task');

    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    foreach ($this->container->get('entity_type.manager')->getDefinitions() as $definition) {
      if ($definition->id() === 'node') {
        $this->assertTrue($definition->hasLinkTemplate('entity-version-history'));
        $this->assertInstanceOf(Route::class, $route_provider->getRouteByName('entity.' . $definition->id() . '.entity_version_history'));
        $history_local_task = $local_task_manager->getDefinition('entity_version_history.entity.history:' . $definition->id());
        $this->assertEquals('entity.' . $definition->id() . '.entity_version_history', $history_local_task['route_name']);
        $this->assertEquals('entity.' . $definition->id() . '.canonical', $history_local_task['base_route']);

        continue;
      }

      // An entity that does not have canonical URL or is not revisionable
      // should not have 'entity-version-history' link template and
      // related route even if it has entity version configuration.
      $this->assertFalse($definition->hasLinkTemplate('entity-version-history'));

      $exception = NULL;
      try {
        $route_provider->getRouteByName('entity.' . $definition->id() . '.entity_version_history');
      }
      catch (\Exception $e) {
        $exception = $e;
      }
      $this->assertInstanceOf(RouteNotFoundException::class, $exception);
    }
  }

  /**
   * Tests access to the entity version history page.
   */
  public function testHistoryPageAccess(): void {
    // Define the anonymous user first. User id with 0 has to exist in order
    // to avoid "ContextException: The 'entity:user' context is required and
    // not present" error.
    User::create([
      'name' => 'Anonymous',
      'uid' => 0,
    ])->save();

    $entity_type_manager = $this->container->get('entity_type.manager');

    $node = $entity_type_manager->getStorage('node')->create([
      'type' => 'first_bundle',
      'title' => 'My node',
    ]);
    $node->save();

    $user_with_permission = $this->createUser(['access entity version history']);
    $user_without_permission = $this->createUser();

    /** @var \Drupal\Core\Access\AccessManager $access_manager */
    $access_manager = $this->container->get('access_manager');
    $cache_contexts = [
      'route',
      'user.permissions',
    ];
    $cache_tags = [
      'config:entity_version.settings.' . $node->getEntityTypeId() . '.' . $node->bundle(),
      'config:entity_version_settings_list',
      $node->getEntityTypeId() . ':' . $node->id(),
    ];

    // Assert that we can't access the history page when no permissions are
    // assigned.
    $access_result = $access_manager->checkNamedRoute('entity.node.entity_version_history', ['node' => $node->id()], $user_without_permission, TRUE);
    $this->assertTrue($access_result->isForbidden());
    $this->assertEquals('Insufficient permissions to access the entity version history page.', $access_result->getReason());
    $this->assertEquals($cache_contexts, $access_result->getCacheContexts());
    $this->assertEqualsCanonicalizing($cache_tags, $access_result->getCacheTags());

    // A user with permissions can access the history page.
    $access_result = $access_manager->checkNamedRoute('entity.node.entity_version_history', ['node' => $node->id()], $user_with_permission, TRUE);
    $this->assertTrue($access_result->isAllowed());
    $this->assertEquals($cache_contexts, $access_result->getCacheContexts());
    $this->assertEqualsCanonicalizing($cache_tags, $access_result->getCacheTags());

    // Remove the corresponding history config.
    $entity_type_manager
      ->getStorage('entity_version_settings')
      ->load('node.first_bundle')
      ->delete();

    // The cache will be different since the config won't be there anymore.
    $cache_contexts = [
      'route',
    ];
    $cache_tags = [
      'config:entity_version_settings_list',
      $node->getEntityTypeId() . ':' . $node->id(),
    ];

    // We can't access the history page without a corresponding
    // history config.
    $access_result = $access_manager->checkNamedRoute('entity.node.entity_version_history', ['node' => $node->id()], $user_with_permission, TRUE);
    $this->assertTrue($access_result->isForbidden());
    $this->assertEquals('No version settings found for this entity type and bundle.', $access_result->getReason());
    $this->assertEquals($cache_contexts, $access_result->getCacheContexts());
    $this->assertEqualsCanonicalizing($cache_tags, $access_result->getCacheTags());
  }

}
