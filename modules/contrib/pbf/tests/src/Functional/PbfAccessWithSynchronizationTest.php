<?php

namespace Drupal\Tests\pbf\Functional;

use Drupal\node\Entity\Node;
use Drupal\field\Entity\FieldConfig;

/**
 * Test access permissions with a Pbf field synchronized.
 *
 * @group pbf
 */
class PbfAccessWithSynchronizationTest extends PbfBaseTest {

  /*
   * Field name to add on article and user.
   *
   * @var string
   */
  protected $fieldname;

  /*
   * Field name to add to group content type.
   *
   * @var string
   */
  protected $group_fieldname;

  /**
   * A node created.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $group3;

  /**
   * Setup and create content with Pbf field.
   */
  public function setUp(): void {
    parent::setUp();

    $this->fieldname = 'field_pbf_group';
    $this->group_fieldname = 'field_group_user';
    $this->attachPbfSynchronizedFields($this->fieldname, $this->group_fieldname);

    $this->article1 = $this->createSimpleArticle('Article 1', $this->fieldname, $this->group1->id(), 0, 1, 0, 0);
    $this->article2 = $this->createSimpleArticle('Article 2', $this->fieldname, $this->group1->id(), 0, 1, 0, 0);
    // Clear field definitions cache in order to avoid stale cache values.
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();
    drupal_flush_all_caches();
    $node_search_plugin = $this->container->get('plugin.manager.search')->createInstance('node_search');
    // Update the search index.
    $node_search_plugin->updateIndex();
  }

  /**
   * Test the "pbf" node access with a Pbf field synchronized.
   */
  public function testPbfAccessWithSynchronization() {

    $this->drupalLogin($this->adminUser);

    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200, 'adminUser is allowed to view the content.');
    $this->drupalGet("node/{$this->article1->id()}/edit");
    // Make sure we don't get a 401 unauthorized response:
    $this->assertSession()->statusCodeEquals(200, 'adminUser is allowed to edit the content.');
    $this->drupalGet("node/{$this->group1->id()}/edit");
    // Make sure we don't get a 401 unauthorized response:
    $this->assertSession()->statusCodeEquals(200, 'adminUser is allowed to edit the group 1.');

    $bundle_path = 'admin/structure/types/manage/article';
    // Check that the field appears in the overview form.
    $this->drupalGet($bundle_path . '/fields');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-overview"]//tr[@id="field-pbf-group"]/td[1]');

    // Check that the field appears in the overview manage display form.
    $this->drupalGet($bundle_path . '/form-display');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-display-overview"]//tr[@id="field-pbf-group"]/td[1]');
    $this->assertSession()->fieldValueEquals('fields[field_pbf_group][type]', 'pbf_widget');

    // Check that the field appears in the overview manage display page.
    $this->drupalGet($bundle_path . '/display');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-display-overview"]//tr[@id="field-pbf-group"]/td[1]');
    $this->assertSession()->fieldValueEquals('fields[field_pbf_group][type]', 'pbf_formatter_default');

    $user_path_config = 'admin/config/people/accounts';
    $this->drupalGet($user_path_config . '/fields');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-overview"]//tr[@id="field-pbf-group"]/td[1]');
    $this->drupalGet($user_path_config . '/form-display');
    $this->assertSession()->fieldValueEquals('fields[field_pbf_group][type]', 'pbf_widget');
    $this->drupalGet($user_path_config . '/display');
    $this->assertSession()->fieldValueEquals('fields[field_pbf_group][type]', 'pbf_formatter_default');

    $bundle_path = 'admin/structure/types/manage/group';
    // Check that the field appears in the overview form.
    $this->drupalGet($bundle_path . '/fields');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-overview"]//tr[@id="field-group-user"]/td[1]');

    // Check that the field appears in the overview manage display form.
    $this->drupalGet($bundle_path . '/form-display');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-display-overview"]//tr[@id="field-group-user"]/td[1]');
    $this->assertSession()->fieldValueEquals('fields[field_group_user][type]', 'pbf_widget');

    // Check that the field appears in the overview manage display page.
    $this->drupalGet($bundle_path . '/display');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-display-overview"]//tr[@id="field-group-user"]/td[1]');
    $this->assertSession()->fieldValueEquals('fields[field_group_user][type]', 'pbf_formatter_default');

    // We change cardinality to limited.
    $field_group_path_storage_settings = 'admin/structure/types/manage/group/fields/node.group.' . $this->group_fieldname . '/storage';
    $edit = [
      'cardinality' => 'number',
      'cardinality_number' => 1,
    ];
    $this->drupalGet($field_group_path_storage_settings);
    $this->submitForm($edit, t('Save field settings'));
    $this->drupalGet($field_group_path_storage_settings);
    $this->assertSession()->fieldValueEquals('cardinality', 'number');

    // The group Pbf field can not be synchronized.
    $field_group_path_settings = 'admin/structure/types/manage/group/fields/node.group.' . $this->group_fieldname;
    $this->drupalGet($field_group_path_settings);
    $this->assertSession()->pageTextContains(t('Only field with an unlimited cardinality can be synchronized'));

    // We change cardinality to unlimited.
    $edit = [
      'cardinality' => -1,
    ];
    $this->drupalGet($field_group_path_storage_settings);
    $this->submitForm($edit, t('Save field settings'));
    $this->drupalGet($field_group_path_storage_settings);
    $this->assertSession()->fieldValueEquals('cardinality', -1);

    // We can select the user Pbf field to be synchronized.
    $this->drupalGet($field_group_path_settings);
    $this->assertSession()->optionExists('edit-settings-synchronized-with', 'user.user.' . $this->fieldname);

    // We change the user's field cardinlaty to limited.
    $user_path_storage_settings = 'admin/config/people/accounts/fields/user.user.' . $this->fieldname . '/storage';
    $edit = [
      'cardinality' => 'number',
      'cardinality_number' => 1,
    ];
    $this->drupalGet($user_path_storage_settings);
    $this->submitForm($edit, t('Save field settings'));
    $this->drupalGet($user_path_storage_settings);
    $this->assertSession()->fieldValueEquals('cardinality', 'number');

    // We can not select the user Pbf field to be synchronized.
    $this->drupalGet($field_group_path_settings);
    $this->assertSession()->optionNotExists('edit-settings-synchronized-with', 'user.user.' . $this->fieldname);

    // We change the user's field cardinlaty to unlimited.
    $edit = [
      'cardinality' => -1,
    ];
    $this->drupalGet($user_path_storage_settings);
    $this->submitForm($edit, t('Save field settings'));
    $this->drupalGet($user_path_storage_settings);
    $this->assertSession()->fieldValueEquals('cardinality', -1);

    // We synchronize the Pbf field (field_group_user) on group with
    // user Pbf field (field_pbf_group).
    $edit = [
      'settings[synchronized_with]' => 'user.user.' . $this->fieldname,
//      'settings[synchronized_from_target]' => '',
    ];
    $this->drupalGet($field_group_path_settings);
    $this->submitForm($edit, t('Save settings'));
    $this->drupalGet($field_group_path_settings);
    $this->assertTrue($this->assertSession()->optionExists('edit-settings-synchronized-with', 'user.user.' . $this->fieldname)->hasAttribute('selected'));
    $this->assertSession()->checkboxNotChecked('edit-settings-synchronized-from-target');

    // Test view access with normal user.
    $this->drupalLogin($this->normalUser);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->pageTextContains(t('Access denied'));
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);

    // Build the search index.
    $this->container->get('cron')->run();
    // Check to see that we find the number of search results expected.
    $this->checkSearchResults('Article', 0);

    // Reference normalUser from group1 node.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet("node/{$this->group1->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);

    $edit = [
      $this->group_fieldname . '[0][target_id]' => $this->normalUser->getDisplayName() . ' (' . $this->normalUser->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->group1->id() . '/edit');
    $this->submitForm($edit, t('Save'));

    $this->drupalGet("user/{$this->normalUser->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    // normalUser has access to node article.
    $this->drupalLogin($this->normalUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->container->get('cron')->run();
    $this->checkSearchResults('Article', 2);

    // otherUser has not access to node article.
    $this->drupalLogin($this->otherUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->checkSearchResults('Article', 0);


    // Reference otherlUser from group1 node.
    $this->drupalLogin($this->adminUser);
    $edit = [
      $this->group_fieldname . '[0][target_id]' => $this->normalUser->getDisplayName() . ' (' . $this->normalUser->id() . ')',
      $this->group_fieldname . '[1][target_id]' => $this->otherUser->getDisplayName() . ' (' . $this->otherUser->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->group1->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->drupalGet("user/{$this->otherUser->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    // otherUser has now access to node article.
    $this->drupalLogin($this->otherUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->checkSearchResults('Article', 2);

    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);

    // Check if users are well displayed in the node group1.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet("node/{$this->group1->id()}");
    $this->assertSession()->linkExists($this->normalUser->getDisplayName());
    $this->assertSession()->linkExists($this->otherUser->getDisplayName());

    // Remove group1 referenced for otherUser.
    $edit = [
      $this->fieldname . '[0][target_id]' => '',
    ];
    $this->drupalGet('/user/' . $this->otherUser->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->linkNotExists($this->group1->getTitle());
    $this->drupalGet("node/{$this->group1->id()}");
    $this->assertSession()->linkExists($this->otherUser->getDisplayName());

    // otherUser does not have access to node article.
    $this->drupalLogin($this->otherUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->checkSearchResults('Article', 0);

    // We add the synchronization from target option in the Pbf field
    // on group with user Pbf field.
    $this->drupalLogin($this->adminUser);
    $edit = [
      'settings[synchronized_with]' => 'user.user.' . $this->fieldname,
      'settings[synchronized_from_target]' => '1',
    ];
    $this->drupalGet($field_group_path_settings);
    $this->submitForm($edit, t('Save settings'));
    $this->drupalGet($field_group_path_settings);
    $this->assertTrue($this->assertSession()->optionExists('edit-settings-synchronized-with', 'user.user.' . $this->fieldname)->hasAttribute('selected'));
    $this->assertSession()->checkboxChecked('edit-settings-synchronized-from-target');

    // We post again otherUser as member of group1.
    $edit = [
      $this->group_fieldname . '[0][target_id]' => $this->normalUser->getDisplayName() . ' (' . $this->normalUser->id() . ')',
      $this->group_fieldname . '[1][target_id]' => $this->otherUser->getDisplayName() . ' (' . $this->otherUser->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->group1->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->drupalGet("user/{$this->otherUser->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    // otherUser has still access to node article.
    $this->drupalLogin($this->otherUser);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->checkSearchResults('Article', 2);

    // Remove group1 referenced for otherUser.
    $edit = [
      $this->fieldname . '[0][target_id]' => '',
    ];
    $this->drupalGet('/user/' . $this->otherUser->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->drupalGet("user/{$this->otherUser->id()}");
    $this->assertSession()->linkNotExists($this->group1->getTitle());
    // otherUser is not referenced from group1.
    $this->drupalGet("node/{$this->group1->id()}");
    $this->assertSession()->linkNotExists($this->otherUser->getDisplayName());
    // otherUser does not have rights access on article.
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(403);


  }

}
