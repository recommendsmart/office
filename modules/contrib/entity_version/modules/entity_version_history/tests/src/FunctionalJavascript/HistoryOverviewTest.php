<?php

declare(strict_types = 1);

namespace Drupal\Tests\entity_version_history\Functional;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Tests the history overview table.
 *
 * @group entity_version_history
 */
class HistoryOverviewTest extends WebDriverTestBase {

  /**
   * An array of node revisions.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $revisions;

  /**
   * A user with administrative permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'entity_version',
    'entity_version_history',
    'entity_version_test',
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

    // Create user.
    $this->adminUser = $this->drupalCreateUser([
      'administer entity version',
      'access administration pages',
      'access entity version history',
      'edit any first_bundle content',
      'administer content types',
    ]);
    $this->drupalLogin($this->adminUser);

    // Create a history tab setting for the corresponding entity type
    // and bundle.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $history_storage = $entity_type_manager->getStorage('entity_version_settings');
    $history_storage->create([
      'target_entity_type_id' => 'node',
      'target_bundle' => 'first_bundle',
      'target_field' => 'field_entity_version',
    ])->save();

    /** @var \Drupal\node\Entity\Node $node */
    $node = $entity_type_manager->getStorage('node')->create([
      'type' => 'first_bundle',
      'title' => 'My test node',
    ]);
    $node->save();

    // Create five revisions.
    $revision_ids[] = $node->getRevisionId();
    $revision_count = 5;
    for ($i = 0; $i < $revision_count; $i++) {
      $version_number = $i + 1;
      // Change the title for each revision.
      $node->set('title', 'My test node ' . $version_number);
      $node->setNewRevision();

      // Increment the version numbers.
      $version = [
        'major' => $version_number,
        'minor' => $version_number,
        'patch' => $version_number,
      ];
      $node->set('field_entity_version', $version);

      // Edit the 1st and 2nd revision with a different user.
      if ($i < 2) {
        $editor = $this->drupalCreateUser();
        $node->setRevisionUserId($editor->id());
      }
      else {
        $node->setRevisionUserId($this->adminUser->id());
      }

      $node->save();
      $revision_ids[] = $node->getRevisionId();
    }

    // Create also a revision in which we do not update the version.
    $node->setNewRevision();
    $node->setRevisionUserId($this->adminUser->id());
    $node->set('title', 'Updated without version change');
    $node->save();
    $revision_ids[] = $node->getRevisionId();

    $this->revisions = $entity_type_manager->getStorage('node')->loadMultipleRevisions($revision_ids);
  }

  /**
   * Tests the history overview table on the page.
   */
  public function testHistoryOverviewTable(): void {
    $revisions = $this->revisions;

    // Get one of the revisions.
    $node = $revisions[7];

    $this->drupalGet($node->toUrl('entity-version-history'));

    $page = $this->getSession()->getPage();
    $date_formatter = $this->container->get('date.formatter');

    // Check we are on the correct page.
    $this->assertEquals('History for Updated without version change', $page->find('css', 'h1')->getText());

    // Confirm the table headers are in place.
    $table_headers = $page->findAll('css', 'th');
    $this->assertCount(4, $table_headers);
    $this->assertEquals('Version', $table_headers[0]->getText());
    $this->assertEquals('Title', $table_headers[1]->getText());
    $this->assertEquals('Date', $table_headers[2]->getText());
    $this->assertEquals('Created by', $table_headers[3]->getText());

    $table_rows = $page->find('css', 'tbody')->findAll('css', 'tr');
    // Even though there are 7 revisions, there should be only 6 table rows
    // because one of the revisions has the same version.
    $this->assertCount(6, $table_rows);
    $this->assertCount(7, $this->revisions);

    foreach ($table_rows as $row_id => $table_row) {
      $row_html = $table_row->getHtml();
      $version_number = 5 - $row_id;
      $revision_id = $version_number + 1;

      // Check that the version number is there.
      $this->assertStringContainsString($version_number . '.' . $version_number . '.' . $version_number, $row_html);

      // Check for the date.
      $this->assertStringContainsString($date_formatter->format($revisions[$revision_id]->get('revision_timestamp')->value, 'short'), $row_html);

      // Original author, and editor names should appear.
      $user = $revisions[$revision_id]->revision_uid->entity;
      $this->assertStringContainsString($user->getAccountName(), $row_html);

      // Check for the correct titles with the correct links.
      switch ($version_number) {
        case 5:
          // The latest and default revision link goes to the node. And since
          // there are two revisions with the same version, the latest one is
          // shown.
          $this->assertStringContainsString('node/' . $node->id(), $row_html);
          $this->assertStringContainsString('Updated without version change', $row_html);
          $this->assertSession()->pageTextNotContains('My test node 5');
          break;

        case 0:
          // The original node did not have the version number in it's title.
          $this->assertStringContainsString('My test node', $row_html);
          $this->assertStringContainsString('node/' . $node->id() . '/revisions/' . $revisions[$revision_id]->getRevisionId() . '/view', $row_html);
          break;

        default:
          // Revision titles contain the version number with a link to
          // the revision.
          $this->assertStringContainsString('My test node ' . $version_number, $row_html);
          $this->assertStringContainsString('node/' . $node->id() . '/revisions/' . $revisions[$revision_id]->getRevisionId() . '/view', $row_html);
          break;
      }
    }
  }

}
