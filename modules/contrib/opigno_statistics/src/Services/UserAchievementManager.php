<?php

namespace Drupal\opigno_statistics\Services;

use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannel;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Session\AccountInterface;
use Drupal\group\GroupMembership;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * UserAchievementManager class.
 */
class UserAchievementManager implements ContainerInjectionInterface {

  public const ACHIEVEMENTS_TABLE = 'opigno_learning_path_achievements';

  public const ACHIEVEMENTS_STEPS_TABLE = 'opigno_learning_path_step_achievements';

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * The database connection service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  private $entityTypeManager;

  /**
   * The logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  private $loggerChannel;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  private $messenger;

  /**
   * UserStatisticsManager constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannel $channel
   *   The logger service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   */
  public function __construct(
    AccountInterface $account,
    Connection $database,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannel $channel,
    Messenger $messenger
  ) {
    $this->database = $database;
    $this->entityTypeManager = $entity_type_manager;
    $this->account = $account;
    $this->loggerChannel = $channel;
    $this->messenger = $messenger;
  }

  /**
   * Class constructor.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('database'),
      $container->get('entity_type.manager'),
      $container->get('logger.opigno_statistics'),
      $container->get('messenger')
    );
  }

  /**
   * Load entities by their property values.
   */
  public function loadGroupByIds($gids) {
    return $this->entityTypeManager->getStorage('group')
      ->loadByProperties(array_filter([
        'id' => $gids ? (array) $gids : NULL,
        'type' => 'learning_path',
      ]));
  }

  /**
   * Stores training achievements data.
   */
  public function opignoLearningPathSaveAchievements(int $gid, int $user_uid) {
    try {
      opigno_learning_path_save_achievements($gid, $user_uid);
    }
    catch (\Exception $e) {
      $this->loggerChannel->error($e->getMessage());
      $this->messenger->addMessage($e->getMessage(), 'error');
    }
  }

  /**
   * Builds up a full list of all the steps in a group for a user.
   */
  public function getLearningPathGetAllSteps(int $gid, int $user_uid) {
    if ($steps = opigno_learning_path_get_all_steps($gid, $user_uid)) {
      foreach ($steps as $step) {
        // Each training steps.
        try {
          // Save current step parent achievements.
          $parent_id = !isset($current_step['parent']) ? 0
            // @TODO We need to ensure that parent exists and function returns id.
            : opigno_learning_path_save_step_achievements($gid, $user_uid, $step['parent']);
          // Save current step achievements.
          opigno_learning_path_save_step_achievements($gid, $user_uid, $step, $parent_id);
        }
        catch (\Exception $e) {
          $this->loggerChannel->error($e->getMessage());
          $this->messenger->addMessage($e->getMessage(), 'error');
        }
      }
    }
  }

  /**
   * Truncate achievements' table helper by uid and gid.
   */
  public function dropAllTables($uid, $gid) {
    if ($uid && $gid) {
      foreach ([
        self::ACHIEVEMENTS_TABLE,
        self::ACHIEVEMENTS_STEPS_TABLE,
      ] as $table) {
        $this->database->delete($table)
          ->condition('uid', $uid)
          ->condition('gid', $gid)
          ->execute();
      }
    }
    else {
      $this->truncateAll();
    }
  }

  /**
   * Truncate achievements' table helper.
   */
  private function truncateAll() {
    foreach ([
      self::ACHIEVEMENTS_TABLE,
      self::ACHIEVEMENTS_STEPS_TABLE,
    ] as $table) {
      $this->database->truncate($table)->execute();
    }
  }

  /**
   * Gets array of members and returns an uniq list.
   *
   * @param \Drupal\group\GroupMembership[] $members
   *   Unique user objects.
   */
  public function getUniqMembers(array $members): array {
    $user_memberships = array_map(function (GroupMembership $group_membership) {
      return $group_membership->getUser();
    }, $members);

    $membership_ids = array_map(function (UserInterface $user) {
      return $user->id();
    }, $user_memberships);

    $unique_membership = array_unique($membership_ids);
    return array_values(array_intersect_key($user_memberships, $unique_membership));
  }

  /**
   * Makes update of trainings statistics.
   */
  public function updateStatistics($uid = NULL, $gid = NULL, ?callable $closure = NULL) {
    /** @var \Drupal\group\Entity\Group[] $groups */
    $groups = $this->loadGroupByIds($gid);
    if (!$groups) {
      return;
    }
    foreach ($groups as $group) {
      $gid = $group->id();
      $this->log($closure, 'Group (' . $gid . ') - "' . $group->label() . '"');
      if ($members = $group->getMembers()) {
        $user_memberships = $this->getUniqMembers($members);
        foreach ($user_memberships as $user) {
          $user_uid = $user->id();
          if ($uid && $uid != $user_uid) {
            continue;
          }
          $this->dropAllTables($user_uid, $gid);
          $this->log($closure, ' - user (' . $user_uid . ') - "' . $user->getDisplayName() . '"');
          $this->opignoLearningPathSaveAchievements($gid, $user_uid);
          $this->getLearningPathGetAllSteps($gid, $user_uid);
        }
      }
    }
  }

  /**
   * Callable log wrapper.
   */
  private function log(?callable $closure, string $string) {
    $closure ? call_user_func($closure, $string) : NULL;
  }

}
