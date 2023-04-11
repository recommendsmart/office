<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_version\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity_version\Entity\EntityVersionSettings;
use Drupal\field\Entity\FieldConfig;
use Drupal\Tests\UnitTestCase;

/**
 * Test EntityVersionSettingsForm entity class.
 *
 * @coversDefaultClass \Drupal\entity_version\Entity\EntityVersionSettings
 * @group entity_version
 */
class EntityVersionSettingsUnitTest extends UnitTestCase {

  /**
   * The entity type used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityType;

  /**
   * The entity type manager used for testing.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * The ID of the type of the entity under test.
   *
   * @var string
   */
  protected $entityTypeId;

  /**
   * The UUID generator used for testing.
   *
   * @var \Drupal\Component\Uuid\UuidInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $uuid;

  /**
   * The typed configuration manager used for testing.
   *
   * @var \Drupal\Core\Config\TypedConfigManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $typedConfigManager;

  /**
   * The config entity storage used for testing.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorage|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $configEntityStorageInterface;

  /**
   * The entity field manager for testing.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityFieldManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->entityTypeId = $this->randomMachineName();
    $this->entityType = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');

    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $this->uuid = $this->createMock('\Drupal\Component\Uuid\UuidInterface');

    $this->typedConfigManager = $this->createMock('Drupal\Core\Config\TypedConfigManagerInterface');

    $this->configEntityStorageInterface = $this->createMock('Drupal\Core\Entity\EntityStorageInterface');

    $this->entityFieldManager = $this->createMock('Drupal\Core\Entity\EntityFieldManagerInterface');

    $container = new ContainerBuilder();
    $container->set('entity_type.manager', $this->entityTypeManager);
    $container->set('uuid', $this->uuid);
    $container->set('config.typed', $this->typedConfigManager);
    $container->set('config.storage', $this->configEntityStorageInterface);
    $container->set('entity_field.manager', $this->entityFieldManager);
    \Drupal::setContainer($container);
  }

  /**
   * @covers ::calculateDependencies()
   */
  public function testCalculateDependencies(): void {
    // Mock the interfaces necessary to create a dependency on a bundle entity.
    $target_entity_type = $this->createMock('\Drupal\Core\Entity\EntityTypeInterface');
    $target_entity_type->expects($this->any())
      ->method('getBundleConfigDependency')
      ->will($this->returnValue([
        'type' => 'config',
        'name' => 'test.test_entity_type.id',
      ]));

    $this->entityTypeManager->expects($this->any())
      ->method('getDefinition')
      ->with('test_entity_type')
      ->will($this->returnValue($target_entity_type));

    // Create a test field config entity and mock the field config entity
    // and the dependency methods.
    $field = new FieldConfig([
      'field_name' => 'field_test',
      'entity_type' => 'test_entity_type',
      'bundle' => 'test_bundle',
      'field_type' => 'test_field',
    ]);
    $field_config = $this->createMock('\Drupal\field\Entity\FieldConfig');
    $field_config->expects($this->any())
      ->method('load')
      ->with($field->id())
      ->will($this->returnValue($field));
    $field_config->expects($this->any())
      ->method('getConfigDependencyName')
      ->will($this->returnValue('field.field.test_entity_type.test_field'));
    $field_config->expects($this->any())
      ->method('getConfigDependencyKey')
      ->will($this->returnValue('config'));

    $field_definitions[$field->getName()] = $field_config;
    $this->entityFieldManager->expects($this->any())
      ->method('getFieldDefinitions')
      ->with('test_entity_type', 'test_bundle')
      ->will($this->returnValue($field_definitions));

    $entity_version_config = new EntityVersionSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
      'target_field' => $field->getName(),
    ], 'entity_version_settings');

    $dependencies = $entity_version_config->calculateDependencies()->getDependencies();

    // Assert that we have the required dependencies for our config entity.
    $this->assertContains('field.field.test_entity_type.test_field', $dependencies['config']);
    $this->assertContains('test.test_entity_type.id', $dependencies['config']);
  }

  /**
   * @covers ::id()
   */
  public function testId(): void {
    $config = new EntityVersionSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
      'target_field' => 'test_field',
    ], 'entity_version_settings');
    $this->assertSame('test_entity_type.test_bundle', $config->id());
  }

  /**
   * @covers ::getTargetEntityTypeId()
   */
  public function testTargetEntityTypeId(): void {
    $config = new EntityVersionSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
      'target_field' => 'test_field',
    ], 'entity_version_settings');
    $this->assertSame('test_entity_type', $config->getTargetEntityTypeId());
  }

  /**
   * @covers ::getTargetBundle()
   */
  public function testTargetBundle(): void {
    $config = new EntityVersionSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
      'target_field' => 'test_field',
    ], 'entity_version_settings');
    $this->assertSame('test_bundle', $config->getTargetBundle());
  }

  /**
   * @covers ::getTargetField()
   */
  public function testTargetField(): void {
    $config = new EntityVersionSettings([
      'target_entity_type_id' => 'test_entity_type',
      'target_bundle' => 'test_bundle',
      'target_field' => 'test_field',
    ], 'entity_version_settings');
    $this->assertSame('test_field', $config->getTargetField());
  }

}
