<?php

namespace Drupal\access_records\EventSubscriber;

use Drupal\access_records\AccessRecordQueryBuilder;
use Drupal\access_records\Entity\AccessRecordType;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\entity\QueryAccess\QueryAccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscriber for query access events to include access records.
 */
class AccessRecordsQueryAccessSubscriber implements EventSubscriberInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The access record query builder.
   *
   * @var \Drupal\access_records\AccessRecordQueryBuilder
   */
  protected AccessRecordQueryBuilder $accessRecordQueryBuilder;

  /**
   * The AccessRecordsQueryAccessSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   * @param \Drupal\access_records\AccessRecordQueryBuilder $aqb
   *   The access record query builder.
   */
  public function __construct(EntityTypeManagerInterface $etm, AccessRecordQueryBuilder $aqb) {
    $this->entityTypeManager = $etm;
    $this->accessRecordQueryBuilder = $aqb;
  }

  /**
   * Includes access records for query access.
   *
   * @param \Drupal\entity\QueryAccess\QueryAccessEvent $event
   *   The event.
   */
  public function onQueryAccess(QueryAccessEvent $event) {
    $conditions = $event->getConditions();

    $account = $event->getAccount();
    $subject_type_id = 'user';
    $subject_id = $account->id();
    if (!($subject = $this->entityTypeManager->getStorage($subject_type_id)->load($subject_id))) {
      // Without a corresponding entity, we cannot have matching records.
      // @todo Clarify whether this goes too far.
      $conditions->addCacheContexts(['user']);
      $conditions->mergeCacheMaxAge(0);
      return;
    }

    $target_type_id = $event->getEntityTypeId();
    $target_type = $this->entityTypeManager->getDefinition($target_type_id);
    $operation = $event->getOperation();

    /** @var \Drupal\Core\Database\Query\SelectInterface[] $queries */
    $queries = [];
    foreach (AccessRecordType::loadForAccessCheck($subject, $target_type_id, $operation, $conditions) as $ar_type) {
      if ($query = $this->accessRecordQueryBuilder->selectByType($ar_type, $subject_id, $operation)) {
        $queries[] = $query;
      }
      else {
        // When the query could not be built, it is because the according type
        // configuration is either incomplete or invalid. Therefore we revoke
        // access overall and make the result uncacheable until it's fixed.
        $conditions->alwaysFalse(TRUE);
        $conditions->mergeCacheMaxAge(0);
        \Drupal::logger('access_records')->alert(sprintf("The configuration of access record type '%s' is incomplete or invalid. Access on '%s' entity types is blocked until that configuration is fixed.", $ar_type->id(), $target_type_id));
        return;
      }
    }
    if (empty($queries)) {
      return;
    }

    $query = array_shift($queries);
    foreach ($queries as $union) {
      $query->union($union);
    }
    $query = \Drupal::database()->select($query, 'ids');
    $query->addField('ids', 'target_id', $target_type->getKey('id'));

    $conditions->alwaysFalse(FALSE);
    $conditions->addCondition($target_type->getKey('id'), $query, 'IN');
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return ['entity.query_access' => 'onQueryAccess'];
  }

}
