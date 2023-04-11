<?php

declare(strict_types = 1);

namespace Drupal\Tests\description_list_field\Functional;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Description list field widget.
 */
class DescriptionListFieldWidgetTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'node',
    'system',
    'field',
    'text',
    'description_list_field',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->container->get('entity_type.manager')->getStorage('node_type')->create([
      'name' => 'Page',
      'type' => 'page',
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'description_list',
      'entity_type' => 'node',
      'type' => 'description_list_field',
      'cardinality' => -1,
    ])->save();

    FieldConfig::create([
      'entity_type' => 'node',
      'field_name' => 'description_list',
      'bundle' => 'page',
      'required' => TRUE,
    ])->save();

    $entity_form_display = EntityFormDisplay::collectRenderDisplay(Node::create(['type' => 'page']), 'default');
    $entity_form_display->setComponent('description_list', [
      'weight' => 1,
      'region' => 'content',
      'type' => 'description_list_widget',
      'settings' => [],
      'third_party_settings' => [],
    ]);
    $entity_form_display->save();
  }

  /**
   * Tests the Description list field widget.
   */
  public function testDescriptionListFieldWidget(): void {
    $user = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($user);

    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('title[0][value]', 'My page');
    // Assert error messages for required fields.
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContainsOnce('Term field is required.');
    $this->assertSession()->pageTextContainsOnce('Description field is required.');
    $this->getSession()->getPage()->fillField('description_list[0][term]', 'Term 1');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContainsOnce('Description field is required.');
    $this->getSession()->getPage()->fillField('description_list[0][term]', '');
    $this->getSession()->getPage()->fillField('description_list[0][description][value]', 'Description 1');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContainsOnce('Term field is required.');
    // Fill in first item.
    $this->getSession()->getPage()->fillField('description_list[0][term]', 'Term 1');
    $this->getSession()->getPage()->fillField('description_list[0][description][value]', 'Description 1');
    // Add another item.
    $this->getSession()->getPage()->pressButton('Add another item');
    $this->getSession()->getPage()->fillField('description_list[1][term]', 'Term 2');
    $this->getSession()->getPage()->fillField('description_list[1][description][value]', 'Description 2');
    $this->getSession()->getPage()->pressButton('Save');
    // Assert page was correctly saved.
    $this->assertSession()->pageTextContains('Page My page has been created');

    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->container->get('entity_type.manager')->getStorage('node')->load(1);
    $expected_values = [
      [
        'term' => 'Term 1',
        'description' => 'Description 1',
        'format' => 'plain_text',
      ],
      [
        'term' => 'Term 2',
        'description' => 'Description 2',
        'format' => 'plain_text',
      ],
    ];
    $this->assertEquals($expected_values, $node->get('description_list')->getValue());

    // Set field optional and assert the node can be saved without providing
    // description list items.
    $field_config = FieldConfig::load('node.page.description_list');
    $field_config->setRequired(FALSE);
    $field_config->save();
    $this->drupalGet('/node/add/page');
    $this->getSession()->getPage()->fillField('title[0][value]', 'My page with optional description list');
    $this->getSession()->getPage()->pressButton('Save');
    $this->assertSession()->pageTextContains('Page My page with optional description list has been created');
  }

}
