<?php

use Drupal\user\Entity\Role;
use Drupal\views\Entity\View;
use Drupal\views\ViewEntityInterface;

/**
 * Migrate "administer storage entities" to "access storage overview".
 */
function storage_post_update_access_overview_permission(&$sandbox = NULL) {
  foreach (Role::loadMultiple() as $role) {
    if ($role->hasPermission('administer storage entities')) {
      $role->grantPermission('access storage overview');
      $role->save();
    }
  }
}

/**
 * Update permission required for `storage` view if it exists.
 */
function storage_post_update_admin_view_permission(&$sandbox = NULL) {
  $view = View::load('storage');
  if (!$view instanceof ViewEntityInterface) {
    return;
  }

  $display =& $view->getDisplay('default');
  if (($display['display_options']['access']['type'] ?? NULL) !== 'perm'
    || ($display['display_options']['access']['options']['perm'] ?? NULL) !== 'administer storage entities') {
    return;
  }

  $display['display_options']['access'] = [
    'type' => 'perm',
    'options' => ['perm' => 'access storage overview'],
  ];

  $view->save();
}
