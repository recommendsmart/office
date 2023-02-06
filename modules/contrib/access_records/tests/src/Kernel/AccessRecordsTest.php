<?php

namespace Drupal\Tests\access_records\Kernel;

use Drupal\access_records\Entity\AccessRecord;
use Drupal\access_records\Entity\AccessRecordType;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\User;

/**
 * Kernel tests for access records.
 *
 * @group access_records
 */
class AccessRecordsTest extends KernelTestBase {

  protected static $modules = [
    'system',
    'user',
    'field',
    'filter',
    'text',
    'node',
    'options',
    'entity',
    'access_records',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('access_record');
    $this->installConfig(static::$modules);
    User::create(['uid' => 0, 'status' => 0, 'name' => ''])->save();
    User::create(['uid' => 1, 'name' => 'admin'])->save();
    User::create(['uid' => 2, 'name' => 'authenticated'])->save();
    // Create the Article content type with revisioning and translation enabled.
    /** @var \Drupal\node\NodeTypeInterface $node_type */
    $node_type = NodeType::create([
      'type' => 'article',
      'name' => 'Article',
      'new_revision' => TRUE,
    ]);
    $node_type->save();
    user_role_grant_permissions(AccountInterface::AUTHENTICATED_ROLE, [
      'access content',
    ]);
  }

  /**
   * Tests create access using access records.
   */
  public function testCreateAccess() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\node\NodeAccessControlHandler $access_control_handler */
    $access_control_handler = \Drupal::entityTypeManager()->getHandler('node', 'access');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();

    $this->assertFalse($published_node->access('view'));

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']));
    $this->assertTrue($published_node->access('view'));

    $account_switcher->switchBack();

    $ar_type = AccessRecordType::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access',
      'label_pattern' => '[access_record:string_representation]',
      'subject_type' => 'user',
      'target_type' => 'node',
      'operations' => ['create', 'view'],
    ]);
    $ar_type->save();
    $ar_type->addDefaultFields();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Reset the runtime cache of node access to take effect.
    $access_control_handler->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']));
    $this->assertFalse($published_node->access('view'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $account_switcher->switchBack();

    $ar1 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_type' => ['article'],
      'subject_roles' => [AccountInterface::AUTHENTICATED_ROLE],
    ]);
    $ar1->save();

    // Reset the runtime cache of node access to take effect.
    $access_control_handler->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']), "Create access must be granted by access record.");
    $this->assertTrue($published_node->access('view'), "View access must be granted by access record.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $account_switcher->switchBack();

    $this->assertFalse($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']), "Create access must not be granted by access record.");
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
  }

  /**
   * Tests view access using access records.
   */
  public function testViewAccess() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished node',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($unpublished_node->access('view'));

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'));
    $this->assertFalse($unpublished_node->access('view'));
    $account_switcher->switchBack();

    $ar_type = AccessRecordType::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access',
      'label_pattern' => '[access_record:string_representation]',
      'subject_type' => 'user',
      'target_type' => 'node',
      'operations' => ['view'],
    ]);
    $ar_type->save();
    $ar_type->addDefaultFields();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($published_node->access('view'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $this->assertFalse($unpublished_node->access('view'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $account_switcher->switchBack();

    $ar1 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_type' => ['article'],
      'subject_roles' => [AccountInterface::AUTHENTICATED_ROLE],
    ]);
    $ar1->save();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'), "View access must be granted by access record.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertTrue($unpublished_node->access('view'), "View access must be granted by access record.");
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $ar2 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_nid' => $published_node->id(),
      'subject_roles' => [AccountInterface::ANONYMOUS_ROLE],
    ]);
    $ar2->save();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $this->assertFalse($published_node->access('view'), "User still has no access, because \Drupal\node\NodeAccessControlHandler revokes access for any user not having permission 'access content'.");
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['access content']);
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertTrue($published_node->access('view'), "Access must now be granted because access record exists.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertTrue($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $ar1->delete();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar2->delete();
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
  }

  /**
   * Tests update access using access records.
   */
  public function testUpdateAccess() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished node',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());

    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($unpublished_node->access('update'));

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($unpublished_node->access('update'));
    $account_switcher->switchBack();

    $ar_type = AccessRecordType::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access',
      'label_pattern' => '[access_record:string_representation]',
      'subject_type' => 'user',
      'target_type' => 'node',
      'operations' => ['update'],
    ]);
    $ar_type->save();
    $ar_type->addDefaultFields();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($published_node->access('update'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $this->assertFalse($unpublished_node->access('update'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $account_switcher->switchBack();

    $ar1 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_type' => ['article'],
      'subject_roles' => [AccountInterface::AUTHENTICATED_ROLE],
    ]);
    $ar1->save();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'), "View access is possible, because user has 'access content' permission and no access record type exists that matches up for this target type with view operations.");
    $this->assertTrue($published_node->access('update'), "Access must be granted by access record.");
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertTrue($unpublished_node->access('update'), "Access must be granted by access record.");
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $ar2 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_nid' => $published_node->id(),
      'subject_roles' => [AccountInterface::ANONYMOUS_ROLE],
    ]);
    $ar2->save();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'), "User still has no access, because \Drupal\node\NodeAccessControlHandler revokes access for any user not having permission 'access content'.");
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['access content']);
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertTrue($published_node->access('view'));
    $this->assertTrue($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'), "View access is possible, because user has 'access content' permission and no access record type exists that matches up for this target type with view operations.");
    $this->assertTrue($published_node->access('update'), "Access must be granted by access record.");
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertTrue($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $ar1->delete();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertTrue($published_node->access('view'), "View access is possible, because user has 'access content' permission and no access record type exists that matches up for this target type with view operations.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar2->delete();
    $this->assertTrue($published_node->access('view'), "User has still access because of 'access content' permission.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
  }

  /**
   * Tests delete access using access records.
   */
  public function testDeleteAccess() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished node',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());

    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('delete'));

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar_type = AccessRecordType::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access',
      'label_pattern' => '[access_record:string_representation]',
      'subject_type' => 'user',
      'target_type' => 'node',
      'operations' => ['delete'],
    ]);
    $ar_type->save();
    $ar_type->addDefaultFields();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($published_node->access('delete'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $this->assertFalse($unpublished_node->access('delete'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $account_switcher->switchBack();

    $ar1 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_type' => ['article'],
      'subject_roles' => [AccountInterface::AUTHENTICATED_ROLE],
    ]);
    $ar1->save();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'), "View access is possible, because user has 'access content' permission and no access record type exists that matches up for this target type with view operations.");
    $this->assertFalse($published_node->access('update'));
    $this->assertTrue($published_node->access('delete'), "Access must be granted by access record.");
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertTrue($unpublished_node->access('delete'), "Access must be granted by access record.");
    $account_switcher->switchBack();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $ar2 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_nid' => $published_node->id(),
      'subject_roles' => [AccountInterface::ANONYMOUS_ROLE],
    ]);
    $ar2->save();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('delete'), "User still has no access, because \Drupal\node\NodeAccessControlHandler revokes access for any user not having permission 'access content'.");
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['access content']);
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertTrue($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertTrue($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'), "View access is possible, because user has 'access content' permission and no access record type exists that matches up for this target type with view operations.");
    $this->assertFalse($published_node->access('update'));
    $this->assertTrue($published_node->access('delete'), "Access must be granted by access record.");
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertTrue($unpublished_node->access('delete'));

    $ar1->delete();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertTrue($published_node->access('view'), "View access is possible, because user has 'access content' permission and no access record type exists that matches up for this target type with view operations.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar2->delete();
    $this->assertTrue($published_node->access('view'), "User has still access because of 'access content' permission.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
  }

  /**
   * Tests combined view, update & delete access using access records.
   */
  public function testCombinedAccess() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();
    $published_node = Node::load($published_node->id());
    $unpublished_node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished node',
      'langcode' => 'en',
      'uid' => 1,
      'status' => 0,
    ]);
    $unpublished_node->save();
    $unpublished_node = Node::load($unpublished_node->id());

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('delete'));

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar_type = AccessRecordType::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access',
      'label_pattern' => '[access_record:string_representation]',
      'subject_type' => 'user',
      'target_type' => 'node',
      'operations' => ['view', 'update', 'delete'],
    ]);
    $ar_type->save();
    $ar_type->addDefaultFields();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar1 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_type' => ['article'],
      'subject_roles' => [AccountInterface::AUTHENTICATED_ROLE],
    ]);
    $ar1->save();

    // Reset the runtime cache of node access to take effect.
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'));
    $this->assertTrue($published_node->access('update'));
    $this->assertTrue($published_node->access('delete'));
    $this->assertTrue($unpublished_node->access('view'));
    $this->assertTrue($unpublished_node->access('update'));
    $this->assertTrue($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $ar2 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_nid' => $published_node->id(),
      'subject_roles' => [AccountInterface::ANONYMOUS_ROLE],
    ]);
    $ar2->save();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();

    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'), "User still has no access, because \Drupal\node\NodeAccessControlHandler revokes access for any user not having permission 'access content'.");
    $this->assertFalse($published_node->access('delete'), "User still has no access, because \Drupal\node\NodeAccessControlHandler revokes access for any user not having permission 'access content'.");
    user_role_grant_permissions(AccountInterface::ANONYMOUS_ROLE, ['access content']);
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertTrue($published_node->access('view'));
    $this->assertTrue($published_node->access('update'));
    $this->assertTrue($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($published_node->access('view'));
    $this->assertTrue($published_node->access('update'));
    $this->assertTrue($published_node->access('delete'));
    $this->assertTrue($unpublished_node->access('view'));
    $this->assertTrue($unpublished_node->access('update'));
    $this->assertTrue($unpublished_node->access('delete'));

    $ar1->delete();
    \Drupal::entityTypeManager()->getHandler('node', 'access')->resetCache();
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
    $account_switcher->switchBack();

    $ar2->delete();
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($unpublished_node->access('view'));
    $this->assertFalse($unpublished_node->access('update'));
    $this->assertFalse($unpublished_node->access('delete'));
  }

  /**
   * Tests field access using access records.
   */
  public function testFieldAccess() {
    /** @var \Drupal\Core\Session\AccountSwitcherInterface $account_switcher */
    $account_switcher = \Drupal::service('account_switcher');

    /** @var \Drupal\node\NodeAccessControlHandler $access_control_handler */
    $access_control_handler = \Drupal::entityTypeManager()->getHandler('node', 'access');

    $published_node = Node::create([
      'type' => 'article',
      'title' => 'Published node',
      'langcode' => 'en',
      'uid' => 2,
      'status' => 1,
    ]);
    $published_node->save();

    $this->assertFalse($published_node->access('view'));

    $ar_type = AccessRecordType::create([
      'langcode' => 'en',
      'status' => TRUE,
      'id' => 'node_access',
      'label_pattern' => '[access_record:string_representation]',
      'subject_type' => 'user',
      'target_type' => 'node',
      'operations' => ['create', 'view'],
      'field_access_enabled' => TRUE,
      'field_access_fields_allowed' => ['title'],
    ]);
    $ar_type->save();
    $ar_type->addDefaultFields();
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    // Reset the runtime cache of node access to take effect.
    $access_control_handler->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertFalse($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']));
    $this->assertFalse($published_node->access('view'), "Access record type for node exists, access must be revoked in general when no access records exist.");
    $account_switcher->switchBack();

    $ar1 = AccessRecord::create([
      'ar_type' => 'node_access',
      'ar_enabled' => 1,
      'ar_uid' => 1,
      'target_type' => ['article'],
      'subject_roles' => [AccountInterface::AUTHENTICATED_ROLE],
    ]);
    $ar1->save();

    // Reset the runtime cache of node access to take effect.
    $access_control_handler->resetCache();

    $account_switcher->switchTo(User::load(2));
    $this->assertTrue($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']), "Create access must be granted by access record.");
    $this->assertTrue($published_node->access('view'), "View access must be granted by access record.");
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertTrue($access_control_handler->fieldAccess('view', $published_node->get('title')->getFieldDefinition(), NULL, $published_node->get('title')));
    $this->assertFalse($access_control_handler->fieldAccess('view', $published_node->get('status')->getFieldDefinition(), NULL, $published_node->get('status')));
    $account_switcher->switchBack();

    $this->assertFalse($access_control_handler->createAccess('article', NULL, ['entity_type_id' => 'node']), "Create access must not be granted by access record.");
    $this->assertFalse($published_node->access('view'));
    $this->assertFalse($published_node->access('update'));
    $this->assertFalse($published_node->access('delete'));
    $this->assertFalse($access_control_handler->fieldAccess('view', $published_node->get('title')->getFieldDefinition(), NULL, $published_node->get('title')));
    $this->assertFalse($access_control_handler->fieldAccess('view', $published_node->get('status')->getFieldDefinition(), NULL, $published_node->get('status')));
  }

}
