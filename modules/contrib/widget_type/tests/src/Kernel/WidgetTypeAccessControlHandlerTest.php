<?php

namespace Drupal\Tests\widget_type\Kernel;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\widget_type\Entity\WidgetType;
use Drupal\widget_type\WidgetTypeAccessControlHandler;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Kernel tests for WidgetTypeAccessControlHandler.
 *
 * @group widget_type
 * @coversDefaultClass \Drupal\widget_type\WidgetTypeAccessControlHandler
 */
class WidgetTypeAccessControlHandlerTest extends KernelTestBase {

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
   * @var \Drupal\widget_type\WidgetTypeAccessControlHandler
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
    $this->theSut = new WidgetTypeAccessControlHandler(
      $this->getProphet()->prophesize(EntityTypeInterface::class)->reveal()
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
   * @covers ::checkAccess
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function testCheckAccess() {
    $account = $this->getProphet()->prophesize(AccountInterface::class);
    $this->assertFalse(
      $this->theSut->access($this->entity, 'view', $account->reveal())
    );
    $account->id()->willReturn(2);
    $account->hasPermission('access content')->willReturn(TRUE);
    $this->assertTrue(
      $this->theSut->access($this->entity, 'view', $account->reveal())
    );
  }

}
