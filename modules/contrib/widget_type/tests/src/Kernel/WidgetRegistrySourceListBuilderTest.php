<?php

namespace Drupal\Tests\widget_type\Kernel;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\widget_type\Entity\WidgetRegistrySource;
use Drupal\widget_type\WidgetRegistrySourceListBuilder;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for WidgetRegistrySourceListBuilder.
 *
 * @group widget_type
 * @coversDefaultClass \Drupal\widget_type\WidgetRegistrySourceListBuilder
 */
class WidgetRegistrySourceListBuilderTest extends KernelTestBase {

  use ProphecyTrait;

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
   * @var \Drupal\widget_type\WidgetRegistrySourceListBuilder
   */
  private $theSut;

  /**
   * The entity.
   *
   * @var \Drupal\widget_type\WidgetRegistrySourceInterface
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
    $this->theSut = new WidgetRegistrySourceListBuilder(
      $this->getProphet()->prophesize(EntityTypeInterface::class)->reveal(),
      $this->getProphet()->prophesize(EntityStorageInterface::class)->reveal()
    );
    $this->entity = WidgetRegistrySource::create([
      'label' => 'The name',
      'id' => 'the_id',
      'endpoint' => 'https://www.example.org/registry.json',
      'status' => TRUE,
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
    self::assertSame('operations', $row['operations']['data']['#type']);
    self::assertSame('The name', $row['label']['data']['#value']);
    self::assertStringStartsWith('color:', $row['label']['data']['#attributes']['style']);
    self::assertStringContainsString('background-color:', $row['label']['data']['#attributes']['style']);
    self::assertSame('widget_type/widget_type.admin', $row['label']['data']['#attached']['library'][0]);
    self::assertSame('https://www.example.org/registry.json', $row['endpoint']);
  }

  /**
   * @covers ::buildHeader
   */
  public function testBuildHeader() {
    self::assertEquals([
      'label' => 'Label',
      'endpoint' => 'Endpoint',
      'status' => 'Status',
      'operations' => 'Operations',
    ], array_map(function (TranslatableMarkup $markup) {
      return $markup->render();
    }, $this->theSut->buildHeader()));
  }

  /**
   * @covers ::getDefaultOperations
   */
  public function testGetDefaultOperations() {
    $operations = $this->theSut->getOperations($this->entity);
    $this->assertEquals([], $operations);
  }

}
