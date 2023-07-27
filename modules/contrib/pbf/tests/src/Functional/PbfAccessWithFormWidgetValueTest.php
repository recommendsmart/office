<?php

namespace Drupal\Tests\pbf\Functional;

/**
 * Test access permissions with Pbf field which reference node.
 *
 * Grants are set on the widget form settings.
 *
 * @group pbf
 */
class PbfAccessWithFormWidgetValueTest extends PbfBaseTest {

  /*
   * Field name to add.
   *
   * @var string
   */
  protected $fieldname;

  /**
   * Setup and create content whith Pbf field.
   */
  public function setUp(): void {
    parent::setUp();

    $this->fieldname = 'field_pbf_group';
    $this->attachPbfNodeFields($this->fieldname);

    // Article 1 is public. Article 2 is private (view only).
    $this->article1 = $this->createSimpleArticle('Article 1', $this->fieldname, $this->group1->id(), 1, 0, 0, 0);
    $this->article2 = $this->createSimpleArticle('Article 2', $this->fieldname, $this->group1->id(), 0, 1, 0, 0);

  }

  /**
   * Test the pbf node access with a Pbf field with grants value from widget.
   */
  public function testPbfAccessWithFormWidget() {

    $this->drupalLogin($this->adminUser);

    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200, 'adminUser is allowed to view the content.');
    $this->drupalGet("node/{$this->article1->id()}/edit");
    // Make sure we don't get a 401 unauthorized response:
    $this->assertSession()->statusCodeEquals(200, 'adminUser is allowed to edit the content.');

    $bundle_path = 'admin/structure/types/manage/article';
    // Check that the field appears in the overview form.
    $this->drupalGet($bundle_path . '/fields');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-overview"]//tr[@id="field-pbf-group"]/td[1]');

    // Check that the field appears in the overview manage display form.
    $this->drupalGet($bundle_path . '/form-display');
    $this->assertSession()->elementExists('xpath', '//table[@id="field-display-overview"]//tr[@id="field-pbf-group"]/td[1]');
    $this->assertSession()->fieldValueEquals('fields[field_pbf_group][type]', 'pbf_widget');
    // Check that grants are not set with the form widget settings.
    $this->assertSession()->pageTextContains(t('Grants access set on each node. Default grant access are :'));

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

    // Test view access with normal user.
    $this->drupalLogin($this->normalUser);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->pageTextContains(t('Access denied'));
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);

    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);

    // Build the search index.
    $this->container->get('cron')->run();
    // Check to see that we find the number of search results expected.
    $this->checkSearchResults('Article', 1);

    // Change settings in the widget form. Now Articles must be all public.
    $settings = [
      'match_operator' => 'CONTAINS',
      'size' => 30,
      'placeholder' => '',
      'grant_global' => 1,
      'grant_public' => 1,
      'grant_view' => 0,
      'grant_update' => 0,
      'grant_delete' => 0,
    ];
    $this->setFormDisplay(
      'node.article.default',
      'node',
      'article',
      $this->fieldname,
      'pbf_widget',
      $settings,
      'default'
    );
    // Check that grants are set generally on the form widget settings.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($bundle_path . '/form-display');
    $this->assertSession()->pageTextContains(t('Grants access set generally. Grant access used are :'));

    // Save articles for acquire new rights access.
    $edit = [
      $this->fieldname . '[0][target_id]' => $this->group1->getTitle() . ' (' . $this->group1->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->article1->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains(t('has been updated'));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    $edit = [
      $this->fieldname . '[0][target_id]' => $this->group1->getTitle() . ' (' . $this->group1->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->article2->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains(t('has been updated'));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    $this->drupalLogin($this->normalUser);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);

    // Nothing has changed for article 1.
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);
    // Search content Article. Must find 2 articles.
    $this->checkSearchResults('Article', 2);

    // Change settings in the widget form. Now Articles must be all private.
    $settings = [
      'match_operator' => 'CONTAINS',
      'size' => 30,
      'placeholder' => '',
      'grant_global' => 1,
      'grant_public' => 0,
      'grant_view' => 1,
      'grant_update' => 0,
      'grant_delete' => 0,
    ];
    $this->setFormDisplay(
      'node.article.default',
      'node',
      'article',
      $this->fieldname,
      'pbf_widget',
      $settings,
      'default'
    );
    // Check that grants are set generally on the form widget settings.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($bundle_path . '/form-display');
    $this->assertSession()->pageTextContains(t('Grants access set generally. Grant access used are :'));
    $this->assertSession()->pageTextContains(t('Grant view:1'));

    // Save articles for acquire new rights access.
    $edit = [
      $this->fieldname . '[0][target_id]' => $this->group1->getTitle() . ' (' . $this->group1->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->article1->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains(t('has been updated'));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    $edit = [
      $this->fieldname . '[0][target_id]' => $this->group1->getTitle() . ' (' . $this->group1->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->article2->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains(t('has been updated'));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    $this->drupalLogin($this->normalUser);

    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);

    // Article 1 must be private now..
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);

    $this->checkSearchResults('Article', 0);

    // Associate normalUser with group1.
    $this->setUserField($this->normalUser->id(), $this->fieldname, ['target_id' => $this->group1->id()]);

    // Check if user is well associated with group1.
    $this->drupalGet("user/{$this->normalUser->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->fieldValueEquals('field_pbf_group[0][target_id]', $this->group1->getTitle() . ' (' . $this->group1->id() . ')');
    $this->drupalGet("user/{$this->normalUser->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());
    $this->assertSession()->statusCodeEquals(200);

    // Check search.
    $this->container->get('cron')->run();
    $this->checkSearchResults('Article', 2);
    // Check view.
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    // Check edit.
    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    // Check delete.
    $this->drupalGet("node/{$this->article2->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);

    // Change settings in the widget form. Now Articles must be all private
    // with all rights access.
    $settings = [
      'match_operator' => 'CONTAINS',
      'size' => 30,
      'placeholder' => '',
      'grant_global' => 1,
      'grant_public' => 0,
      'grant_view' => 1,
      'grant_update' => 1,
      'grant_delete' => 1,
    ];
    $this->setFormDisplay(
      'node.article.default',
      'node',
      'article',
      $this->fieldname,
      'pbf_widget',
      $settings,
      'default'
    );
    // Check that grants are set generally on the form widget settings.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet($bundle_path . '/form-display');
    $this->assertSession()->pageTextContains(t('Grants access set generally. Grant access used are :'));
    $this->assertSession()->pageTextContains(t('Grant update:1'));
    $this->assertSession()->pageTextContains(t('Grant delete:1'));

    // Save articles for acquire new rights access.
    $edit = [
      $this->fieldname . '[0][target_id]' => $this->group1->getTitle() . ' (' . $this->group1->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->article1->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains(t('has been updated'));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    $edit = [
      $this->fieldname . '[0][target_id]' => $this->group1->getTitle() . ' (' . $this->group1->id() . ')',
    ];
    $this->drupalGet('/node/' . $this->article2->id() . '/edit');
    $this->submitForm($edit, t('Save'));
    $this->assertSession()->pageTextContains(t('has been updated'));
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->linkExists($this->group1->getTitle());

    $this->drupalLogin($this->normalUser);

    // Check view.
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(200);
    // Check edit.
    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(200);
    // Check delete.
    $this->drupalGet("node/{$this->article2->id()}/delete");
    $this->assertSession()->statusCodeEquals(200);
    $this->drupalGet("node/{$this->article1->id()}/delete");
    $this->assertSession()->statusCodeEquals(200);

    // Test with anonymous user.
    $this->drupalLogout();
    $this->drupalGet("node/{$this->article1->id()}");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article2->id()}");
    $this->assertSession()->statusCodeEquals(403);
    // Check edit.
    $this->drupalGet("node/{$this->article2->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/edit");
    $this->assertSession()->statusCodeEquals(403);
    // Check delete.
    $this->drupalGet("node/{$this->article2->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet("node/{$this->article1->id()}/delete");
    $this->assertSession()->statusCodeEquals(403);

  }

}
