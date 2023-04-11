<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_version\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;

/**
 * Tests the EntityVersionInstaller service.
 */
class EntityVersionInstallerTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * The node type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $nodeType;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'field',
    'text',
    'system',
    'workflows',
    'content_moderation',
    'entity_version',
    'entity_version_workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'user',
      'node',
      'system',
      'field',
      'workflows',
      'content_moderation',
      'entity_version',
    ]);
    $this->installEntitySchema('node');
    $this->installEntitySchema('content_moderation_state');
    $this->installSchema('system', ['sequences']);

    $this->nodeType = $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Test node type',
      'type' => 'test_node_type',
    ]);

    $this->nodeType->save();

    $workflow = $this->createEditorialWorkflow();
    $workflow->getTypePlugin()
      ->addEntityTypeAndBundle('node', 'test_node_type');

    $workflow->save();
  }

  /**
   * Tests the entity version installation.
   */
  public function testEntityVersionInstallationService(): void {
    // Assert we don't have the field added by the installer.
    $this->assertEmpty($this->container->get('entity_type.manager')->getStorage('field_config')->load("node.{$this->nodeType->id()}.version"));

    // Add the field by the installer service with the following default value.
    $default_value = [
      'major' => 0,
      'minor' => 1,
      'patch' => 0,
    ];

    $this->container->get('entity_version.entity_version_installer')->install('node', [$this->nodeType->id()], $default_value);
    /** @var \Drupal\field\Entity\FieldConfig $field_config */
    $field_config = $this->container->get('entity_type.manager')->getStorage('field_config')->load("node.{$this->nodeType->id()}.version");
    // Assert that the field is added by the installer.
    $this->assertInstanceOf(FieldConfig::class, $field_config);
    $actual_default_value = $field_config->getDefaultValueLiteral();
    $this->assertEquals($default_value, reset($actual_default_value));
  }

}
