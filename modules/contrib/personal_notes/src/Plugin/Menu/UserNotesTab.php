<?php

namespace Drupal\personal_notes\Plugin\Menu;

use Drupal\Core\Menu\LocalTaskDefault;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\user\UserInterface;

/**
 * Added current user id to menu path.
 */
class UserNotesTab extends LocalTaskDefault {

  /**
   * {@inheritdoc}
   */
  public function getRouteParameters(RouteMatchInterface $route_match): array {
    $user = $route_match->getParameter('user');
    if ($user instanceof UserInterface) {
      $uid = $user->id();
    }
    else {
      $uid = $user;
    }

    return [
      'user' => $uid,
    ];
  }

}
