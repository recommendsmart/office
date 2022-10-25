<?php

namespace Drupal\Tests\widget_type\Kernel;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\widget_type\Entity\WidgetType;
use Drupal\widget_type\WidgetTypeListBuilder;

/**
 * Kernel tests for WidgetTypeListBuilder.
 *
 * @group widget_type
 * @coversDefaultClass \Drupal\widget_type\WidgetTypeListBuilder
 */
class WidgetTypeListBuilderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'widget_type',
    'field',
    'image',
    'file',
    'text',
    'user',
    'system',
  ];

  /**
   * The system under test.
   *
   * @var \Drupal\widget_type\WidgetTypeListBuilder
   */
  private $theSut;

  /**
   * The entity.
   *
   * @var \Drupal\widget_type\WidgetTypeInterface
   */
  private $entity;

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::setContainer($this->container);
    $this->installEntitySchema('widget_type');
    $this->installEntitySchema('user');
    $this->installConfig(['field', 'widget_type', 'user']);
    $this->theSut = new WidgetTypeListBuilder(
      $this->prophesize(EntityTypeInterface::class)->reveal(),
      $this->prophesize(EntityStorageInterface::class)->reveal(),
      $this->prophesize(DateFormatterInterface::class)->reveal(),
      $this->prophesize(RedirectDestinationInterface::class)->reveal()
    );
    $this->entity = WidgetType::create([
      'name' => 'The name',
      'remote_widget_id' => 'remote-id',
      'remote_widget_version' => 'v1.2.3',
      'remote_widget_directory' => 'https://the-s3/path',
    ]);
    $this->entity->save();
  }

  /**
   * @covers ::buildRow
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function testBuildRow() {
    $row = $this->theSut->buildRow($this->entity);
    self::assertIsArray($row['title']);
    self::assertSame('operations', $row['operations']['data']['#type']);
    self::assertSame('Enabled', $row['status']['data']['#value']->render());
    self::assertSame('https://the-s3/path', $row['directory']['data']['#value']);
    self::assertSame('v1.2.3', $row['version']['data']['#value']);
  }

  /**
   * @covers ::buildHeader
   */
  public function testBuildHeader() {
    $header = $this->theSut->buildHeader();
    self::assertEquals([
      'status',
      'title',
      'changed',
      'source',
      'directory',
      'version',
      'operations',
    ], array_keys($header));
    self::assertNull($header['status']);
  }

  /**
   * @covers ::getDefaultOperations
   */
  public function testGetDefaultOperations() {
    $operations = $this->theSut->getOperations($this->entity);
    $this->assertEquals([], $operations);
  }

}
