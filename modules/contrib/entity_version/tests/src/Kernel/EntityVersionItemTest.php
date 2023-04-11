<?php

namespace Drupal\Tests\entity_version\Kernel;

use Drupal\entity_test\Entity\EntityTest;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\entity_version\Traits\EntityVersionAssertionsTrait;
use Drupal\Tests\field\Kernel\FieldKernelTestBase;

/**
 * Tests the new entity API for the entity version field type.
 */
class EntityVersionItemTest extends FieldKernelTestBase {

  use EntityVersionAssertionsTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['entity_version'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a generic field for validation.
    FieldStorageConfig::create([
      'field_name' => 'field_version',
      'entity_type' => 'entity_test',
      'type' => 'entity_version',
    ])->save();
    FieldConfig::create([
      'entity_type' => 'entity_test',
      'field_name' => 'field_version',
      'bundle' => 'entity_test',
    ])->save();
  }

  /**
   * Tests using entity fields of the entity version field type.
   */
  public function testEntityVersionItem() {
    // Create entity.
    $entity = EntityTest::create();
    $entity->save();

    // Verify that the field default value is zero.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 0, 0, 0);

    // Use the field type method to increase the values.
    $entity->field_version->first()->increase('major');
    $entity->field_version->first()->increase('minor');
    $entity->field_version->first()->increase('patch');
    $entity->save();

    // Verify that the field value is changed.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 1, 1, 1);

    // Use the field type method to decrease the major number.
    $entity->field_version->first()->decrease('major');
    $entity->save();

    // Verify that the field value is changed.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 0, 1, 1);

    // Use the field type method to decrease values.
    $entity->field_version->first()->decrease('minor');
    $entity->field_version->first()->decrease('patch');
    $entity->save();

    // Verify that the field value is changed.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 0, 0, 0);

    // Use the field type method to increase the values.
    $entity->field_version->first()->increase('major');
    $entity->field_version->first()->increase('minor');
    $entity->field_version->first()->increase('patch');
    $entity->save();

    // Use the field type method to reset values to zero.
    $entity->field_version->first()->reset('major');
    $entity->field_version->first()->reset('minor');
    $entity->field_version->first()->reset('patch');
    $entity->save();

    // Verify that the field value is reset.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 0, 0, 0);

    // Use the field type method to decrease values.
    $entity->field_version->first()->decrease('major');
    $entity->field_version->first()->decrease('minor');
    $entity->field_version->first()->decrease('patch');
    $entity->save();

    // Verify that the field value has not changed.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 0, 0, 0);

    // Use the field type method to increase patch.
    $entity->field_version->first()->increase('patch');
    $entity->save();

    // Verify that the field value is changed.
    $id = $entity->id();
    $entity = EntityTest::load($id);
    $this->assertEntityVersion($entity, 0, 0, 1);
  }

}
