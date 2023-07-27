<?php

namespace Drupal\Tests\route_override\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Simple browser test.
 *
 * @group entity_unified_access
 */
class AdminPageTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'route_override',
  ];

  /**
   * Theme to enable.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests that the /admin page returns a 200.
   */
  public function testAdminPage() {
    $this->drupalLogin($this->rootUser);
    $this->drupalGet('admin');
    $this->assertSession()->statusCodeEquals(200);
  }

}
