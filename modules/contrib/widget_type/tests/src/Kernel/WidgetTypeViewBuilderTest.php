<?php

namespace Drupal\Tests\widget_type\Kernel;

use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\Registry;
use Drupal\KernelTests\KernelTestBase;
use Drupal\widget_type\Entity\WidgetType;
use Drupal\widget_type\WidgetTypeViewBuilder;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for WidgetTypeViewBuilder.
 *
 * @group widget_type
 * @coversDefaultClass \Drupal\widget_type\WidgetTypeViewBuilder
 */
class WidgetTypeViewBuilderTest extends KernelTestBase {

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
   * @var \Drupal\widget_type\WidgetTypeViewBuilder
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
    $this->theSut = new WidgetTypeViewBuilder(
      $this->getProphet()->prophesize(EntityTypeInterface::class)->reveal(),
      $this->getProphet()->prophesize(EntityRepositoryInterface::class)->reveal(),
      $this->getProphet()->prophesize(LanguageManagerInterface::class)->reveal(),
      $this->getProphet()->prophesize(Registry::class)->reveal(),
      $this->getProphet()->prophesize(EntityDisplayRepositoryInterface::class)->reveal()
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
   * @covers ::view
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function testView() {
    $build = $this->theSut->view($this->entity, 'library');
    self::assertArrayHasKey('#attached', $build);
    self::assertSame(
      ['library' => ['widget_type/widget_type.remote-id.1']],
      $build['#attached']
    );
  }

}
