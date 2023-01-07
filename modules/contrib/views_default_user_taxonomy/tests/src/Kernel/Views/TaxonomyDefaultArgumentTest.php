<?php

namespace Drupal\Tests\views_default_user_taxonomy\Kernel\Views;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\views\Tests\ViewTestData;
use Drupal\views\Views;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Drupal\Tests\taxonomy\Kernel\Views\TaxonomyTestBase;

/**
 * Tests the that the taxonomy terms are loaded from a user entity when viewed.
 *
 * @group views_default_user_taxonomy
 */
class TaxonomyDefaultArgumentTest extends TaxonomyTestBase {

  /**
   * Views used by this test.
   *
   * @var array
   */
  public static $testViews = ['taxonomy_default_argument_user_test'];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'taxonomy',
    'taxonomy_test_views',
    'views_default_user_taxonomy',
    'views_default_user_taxonomy_test_views',
    'text',
    'node',
    'field',
    'filter',
    'user'
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp($import_test_views = FALSE): void {
    parent::setUp(FALSE);
    ViewTestData::createTestViews(static::class, ['views_default_user_taxonomy_test_views']);

    $handler_settings = [
      'target_bundles' => [
        $this->vocabulary->id() => $this->vocabulary->id(),
      ],
      'auto_create' => TRUE,
    ];

    // Add the taxonomy field to the user entity.
    $this->createEntityReferenceField(
      'user',
      'user',
      'field_views_testing_tags',
      'Tags',
      'taxonomy_term',
      'default',
      $handler_settings,
      FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED
    );
  }

  /**
   * Init view with a request by provided url.
   *
   * @param string $request_url
   *   The requested url.
   * @param string $view_name
   *   The name of the view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The initiated view.
   *
   * @throws \Exception
   */
  protected function initViewWithRequest($request_url, $view_name = 'taxonomy_default_argument_user_test') {
    $view = Views::getView($view_name);

    $request = Request::create($request_url);
    $request->server->set('SCRIPT_NAME', $GLOBALS['base_path'] . 'index.php');
    $request->server->set('SCRIPT_FILENAME', 'index.php');

    $response = $this->container->get('http_kernel')
      ->handle($request, HttpKernelInterface::SUB_REQUEST);

    $view->setRequest($request);
    $view->setResponse($response);
    $view->initHandlers();

    return $view;
  }

  /**
   * Tests the taxonomy term is returned from the user entity.
   */
  public function testUserPath() {
    $account = $this->createUser(
      [],
      NULL,
      FALSE,
      [
        'field_views_testing_tags' => [
          [
            'target_id' => $this->term1->id()
          ]
        ]
      ]
    );

    $view = $this->initViewWithRequest($account->toUrl()->toString());
    $expected = implode(',', [$this->term1->id()]);
    $this->assertEquals($expected, $view->argument['tid']->getDefaultArgument());
    $view->destroy();
  }
}
