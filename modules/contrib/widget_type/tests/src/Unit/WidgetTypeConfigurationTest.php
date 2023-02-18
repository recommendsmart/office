<?php

namespace Drupal\Tests\widget_type\Unit;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Tests\UnitTestCase;
use Drupal\widget_type\WidgetTypeConfiguration;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * Unit tests for the WidgetTypeConfiguration.
 *
 * @group widget_type
 *
 * @coversDefaultClass \Drupal\widget_type\WidgetTypeConfiguration
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
final class WidgetTypeConfigurationTest extends UnitTestCase {

  use ProphecyTrait;

  /**
   * @covers ::shouldIngestAssets
   * @dataProvider shouldIngestAssetsProvider
   */
  public function testShouldIngestAssets($name, $config, $expected) {
    $config_object = $this->getProphet()->prophesize(ImmutableConfig::class);
    [$allowed, $disallowed] = $config;
    $config_object->get('download_assets.allowed_list')->willReturn($allowed);
    $config_object->get('download_assets.disallowed_list')->willReturn($disallowed);
    $sut = new WidgetTypeConfiguration($config_object->reveal());
    self::assertSame($expected, $sut->shouldIngestAssets($name));
  }

  public function shouldIngestAssetsProvider() {
    return [
      // All disabled.
      ['foo', [[], []], FALSE],
      // Some allowed but not 'foo'.
      ['foo', [['bar'], []], FALSE],
      // Some allowed including 'foo'.
      ['foo', [['foo', 'oof'], []], TRUE],
      // If allowed and disallowed, then only consider allowed.
      ['foo', [['foo'], ['foo']], TRUE],
      ['foo', [['bar'], ['foo']], FALSE],
      // Some disallowed including 'foo'.
      ['foo', [[], ['foo', 'oof']], FALSE],
      // Some disallowed but not 'foo'.
      ['foo', [[], ['bar']], TRUE],
    ];
  }

}
