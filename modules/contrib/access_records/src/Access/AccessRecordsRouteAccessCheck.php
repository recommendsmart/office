<?php

namespace Drupal\access_records\Access;

use Drupal\access_records\AccessRecordQueryBuilder;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Symfony\Component\Routing\Route;

/**
 * Determines access to routes based on access records.
 *
 * You can specify the '_access_record' key on route requirements, where the
 * value must be the machine name (ID) of an access record type. If you
 * specify a single type of access record, users having at least one access
 * record of the specified type will be granted for access. If you specify
 * multiple ones you can conjunct them with AND by using a "," and with OR by
 * using "+".
 *
 * By default, this access check involves the "view" operation. If you want to
 * use a different operation for the access check (like "update", "delete" etc.)
 * then you need to additionally specify '_access_operation' as route
 * requirement.
 *
 * Please note that usually you don't need this as a route requirement, when
 * the route itself belongs to a certain entity. For that, entity access is
 * already being handled with access records.
 */
class AccessRecordsRouteAccessCheck implements AccessInterface {

  /**
   * Checks access.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, AccountInterface $account) {
    // Requirements just allow strings, so this might be a comma separated list.
    $ar_type_string = $route->getRequirement('_access_record');
    $operation = $route->hasRequirement('_access_operation') ? $route->getRequirement('_access_operation') : 'view';

    if (isset($ar_type_string)) {
      $storage = \Drupal::entityTypeManager()->getStorage('access_record_type');
      $query_builder = AccessRecordQueryBuilder::get();
      $user = User::load($account->id());

      $cacheability = new CacheableMetadata();
      $cacheability->addCacheContexts(['user']);
      $cacheability->addCacheTags(['config:access_record_type_list']);

      if ($user) {
        $cacheability->addCacheableDependency($user);

        foreach ($user->getRoles() as $rid) {
          if ($role = Role::load($rid)) {
            if ($role->isAdmin()) {
              return AccessResult::allowed()->addCacheableDependency($cacheability);
            }
          }
        }

        $explode_and = array_filter(array_map('trim', explode(',', $ar_type_string)));
        $matching_ar_types = [];
        if (count($explode_and) > 1) {
          foreach ($storage->loadMultiple($explode_and) as $ar_type) {
            $cacheability->addCacheableDependency($ar_type);
            $cacheability->addCacheTags(['access_record_list:' . $ar_type->id()]);
            $query = $query_builder->queryByType($ar_type, $user, NULL, $operation);
            if (count($query->range(0, 1)->execute()) > 0) {
              $matching_ar_types[] = $ar_type;
            }
          }
          if (count($matching_ar_types) === count($explode_and)) {
            return AccessResult::allowed()->addCacheableDependency($cacheability);
          }
        }
        else {
          $explode_or = array_filter(array_map('trim', explode('+', $ar_type_string)));
          foreach ($storage->loadMultiple($explode_or) as $ar_type) {
            $cacheability->addCacheableDependency($ar_type);
            $cacheability->addCacheTags(['config:access_record_type_list', 'access_record_list:' . $ar_type->id()]);
            $query = $query_builder->queryByType($ar_type, $user, NULL, $operation);
            if (count($query->range(0, 1)->execute()) > 0) {
              $matching_ar_types[] = $ar_type;
            }
          }
          if (!empty($matching_ar_types)) {
            return AccessResult::allowed()->addCacheableDependency($cacheability);
          }
        }
      }
    }

    // If there is no allowed role, give other access checks a chance.
    return AccessResult::neutral()->addCacheableDependency($cacheability);
  }

}
