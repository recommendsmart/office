<?php

namespace Drupal\access_records\Entity;

use Drupal\access_records\AccessRecordTypeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the access record type entity.
 *
 * @ConfigEntityType(
 *   id = "access_record_type",
 *   label = @Translation("Access record type"),
 *   label_collection = @Translation("Access record types"),
 *   bundle_label = @Translation("Access record type"),
 *   label_singular = @Translation("access record type"),
 *   label_plural = @Translation("access record types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count access record type",
 *     plural = "@count access record types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\access_records\Form\AccessRecordTypeForm",
 *       "edit" = "Drupal\access_records\Form\AccessRecordTypeForm",
 *       "delete" = "Drupal\access_records\Form\AccessRecordTypeDeleteConfirm",
 *     },
 *     "list_builder" = "Drupal\access_records\AccessRecordTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     }
 *   },
 *   admin_permission = "administer access_record_type",
 *   bundle_of = "access_record",
 *   config_prefix = "type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "status" = "status",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/access-record/add",
 *     "edit-form" = "/admin/structure/access-record/manage/{access_record_type}",
 *     "delete-form" = "/admin/structure/access-record/manage/{access_record_type}/delete",
 *     "collection" = "/admin/structure/access-record"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "uuid",
 *     "status",
 *     "new_revision",
 *     "label_pattern",
 *     "subject_type",
 *     "target_type",
 *     "operations",
 *     "field_access_enabled",
 *     "field_access_fields_allowed"
 *   }
 * )
 */
class AccessRecordType extends ConfigEntityBundleBase implements AccessRecordTypeInterface {

  /**
   * The machine name of this access record type.
   *
   * @var string
   */
  protected $id;

  /**
   * The human-readable name of the access record type.
   *
   * @var string
   */
  protected $label;

  /**
   * A brief description of this access record type.
   *
   * @var string
   */
  protected $description;

  /**
   * Whether access records should be enabled by default.
   *
   * @var bool
   */
  protected $status = TRUE;

  /**
   * Whether a new revision should be created by default.
   *
   * @var bool
   */
  protected $new_revision = TRUE;

  /**
   * A pattern to use for creating the label of the access record.
   *
   * @var string
   */
  protected string $label_pattern = '[access_record:string_representation]';

  /**
   * The assigned subject entity type (mostly "user").
   *
   * @var string|null
   */
  protected ?string $subject_type = 'user';

  /**
   * The assigned target entity type access records of this type belong to.
   *
   * @var string|null
   */
  protected ?string $target_type = NULL;

  /**
   * Allowed operations.
   *
   * @var string[]
   */
  protected array $operations = [];

  /**
   * Whether field access is enabled.
   *
   * @var bool
   */
  protected bool $field_access_enabled = FALSE;

  /**
   * List of allowed fields to access.
   *
   * This is only relevant when field access is enabled (field_access_enabled).
   *
   * @var string[]
   */
  protected array $field_access_fields_allowed = [];

  /**
   * {@inheritdoc}
   */
  public function shouldCreateNewRevision() {
    return $this->new_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription($description) {
    return $this->set('description', $description);
  }

  /**
   * {@inheritdoc}
   */
  public function getStatus(): bool {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status): AccessRecordTypeInterface {
    $this->status = (bool) $status;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabelPattern(): string {
    return $this->label_pattern;
  }

  /**
   * {@inheritdoc}
   */
  public function setLabelPattern($pattern): AccessRecordTypeInterface {
    $this->label_pattern = $pattern;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubjectType(): ?EntityTypeInterface {
    if ($subject_type_id = $this->getSubjectTypeId()) {
      return \Drupal::entityTypeManager()->getDefinition($subject_type_id);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubjectTypeId(): ?string {
    return $this->subject_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setSubjectTypeId(string $entity_type_id): AccessRecordTypeInterface {
    $this->subject_type = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetType(): ?EntityTypeInterface {
    if ($target_type_id = $this->getTargetTypeId()) {
      return \Drupal::entityTypeManager()->getDefinition($target_type_id);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetTypeId(): ?string {
    return $this->target_type;
  }

  /**
   * {@inheritdoc}
   */
  public function setTargetTypeId(string $entity_type_id): AccessRecordTypeInterface {
    $this->target_type = $entity_type_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperations(): array {
    return $this->operations;
  }

  /**
   * {@inheritdoc}
   */
  public function setOperations(array $operations): AccessRecordTypeInterface {
    $this->operations = $operations;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSubjectFieldNames(): array {
    return $this->extractMatchingFieldNames('subject');
  }

  /**
   * {@inheritdoc}
   */
  public function getTargetFieldNames(): array {
    return $this->extractMatchingFieldNames('target');
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $types = [];
    if ($subject_type = $this->getSubjectTypeId()) {
      $types[] = $subject_type;
    }
    if ($target_type = $this->getTargetTypeId()) {
      $types[] = $target_type;
    }
    foreach ($types as $type_id) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($type_id);
      $provider = $entity_type->getProvider();
      if (!in_array($provider, ['core', 'component'])) {
        $this->addDependency('module', $provider);
      }
    }
    return parent::calculateDependencies();
  }

  /**
   * {@inheritdoc}
   */
  public function addDefaultFields(): AccessRecordTypeInterface {
    \Drupal::service('access_records.field_builder')->addDefaultFields($this);
    return $this;
  }

  /**
   * Helper method for extracting matching field names.
   *
   * @param string $type
   *   The relation type of matching fields, either "subject" or "target".
   *
   * @return string[]
   *   The extracted list of matching fields, keyed by field name within the
   *   access record, whereas values are the field name on the subject/target.
   */
  protected function extractMatchingFieldNames(string $type): array {
    if (!in_array($type, ['subject', 'target'])) {
      return [];
    }

    if ($type == 'subject') {
      $entity_type_id = $this->getSubjectTypeId();
      $exclude_prefixes =
        [3 => 'ar_', 9 => 'field_ar_', 7 => 'target_', 13 => 'field_target_'];
      $clear_prefixes = [8 => 'subject_', 14 => 'field_subject_'];
    }
    else {
      $entity_type_id = $this->getTargetTypeId();
      $exclude_prefixes =
        [3 => 'ar_', 9 => 'field_ar_', 8 => 'subject_', 14 => 'field_subject_'];
      $clear_prefixes = [7 => 'target_', 13 => 'field_target_'];
    }

    if (!$entity_type_id) {
      return [];
    }

    $field_names = [];
    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $efm */
    $efm = \Drupal::service('entity_field.manager');
    $fields = $efm->getFieldStorageDefinitions($entity_type_id);
    foreach ($efm->getFieldDefinitions('access_record', $this->id()) as $ar_field_name => $field_definition) {
      if ($field_definition->isComputed() || $field_definition->getFieldStorageDefinition()->isBaseField()) {
        continue;
      }

      foreach (explode('__', $ar_field_name) as $field_name) {
        foreach ($exclude_prefixes as $len => $prefix) {
          if (substr($field_name, 0, $len) === $prefix) {
            continue 2;
          }
        }

        foreach ($clear_prefixes as $len => $prefix) {
          if (substr($field_name, 0, $len) === $prefix) {
            $field_name = substr($field_name, $len);
            break;
          }
        }

        if (isset($fields[$field_name])) {
          $field_names[$ar_field_name] = $field_name;
        }
        elseif ((substr($field_name, 0, 6) === 'field_') && isset($fields[substr($field_name, 6)])) {
          $field_names[$ar_field_name] = substr($field_name, 6);
        }
      }
    }

    return $field_names;
  }

  /**
   * Returns all types of records that should be accounted for an access check.
   *
   * Additionally adds cacheability metadata to the given metadata object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $subject
   *   The subject, which is mostly a user entity.
   * @param string $target_type_id
   *   The entity type ID of the target.
   * @param string $operation
   *   The requested entity operation.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $metadata
   *   (optional) The object that holds collected cacheability metadata.
   * @param bool $quick_check
   *   (optional) By default, a quick check is performed to see whether it
   *   makes sense to even look for access record types.
   * @param bool $field_access_check
   *   (optional) When performing a field access check, then this flag needs to
   *   be set to TRUE.
   *
   * @return \Drupal\access_records\AccessRecordTypeInterface[]
   *   The access record types. Can be empty.
   */
  public static function loadForAccessCheck(EntityInterface $subject, string $target_type_id, string $operation, ?RefinableCacheableDependencyInterface $metadata = NULL, bool $quick_check = TRUE, bool $field_access_check = FALSE): array {
    $subject_type_id = $subject->getEntityTypeId();

    if (!isset($metadata)) {
      $metadata = new CacheableMetadata();
    }
    $etm = \Drupal::entityTypeManager();

    if ($quick_check && $subject instanceof UserInterface) {
      // Quick lookup whether it makes even sense to build access conditions.
      // This also prevents admins from excluding themselves for content access.
      $metadata->addCacheContexts(['user.roles']);
      $role_storage = $etm->getStorage('user_role');
      foreach ($subject->getRoles(TRUE) as $role_id) {
        /** @var \Drupal\user\RoleInterface $role */
        if ($role = $role_storage->load($role_id)) {
          if ($role->isAdmin()) {
            return [];
          }
        }
      }
    }

    $ar_type_storage = $etm->getStorage('access_record_type');
    $metadata->addCacheTags(['config:access_record_type_list']);

    $ar_type_query = $ar_type_storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', TRUE)
      ->condition('subject_type', $subject_type_id)
      ->condition('target_type', $target_type_id)
      ->condition('operations.*', $operation);

    if ($field_access_check) {
      $ar_type_query
        ->condition('field_access_enabled', TRUE);
    }

    $ar_type_ids = $ar_type_query
      ->execute();

    if (empty($ar_type_ids)) {
      return [];
    }

    // Add cacheability metadata.
    $list_cache_tags = [];
    foreach ($ar_type_ids as $type) {
      $list_cache_tags[] = 'access_record_list:' . $type;
    }
    $metadata->addCacheTags($list_cache_tags);
    if ($subject->isNew()) {
      $metadata->mergeCacheMaxAge(0);
    }
    else {
      $metadata->addCacheTags([$subject_type_id . ':' . $subject->id()]);
    }
    if (\Drupal::hasService('cache_context.' . $subject_type_id)) {
      $metadata->addCacheContexts([$subject_type_id]);
    }
    else {
      $metadata->mergeCacheMaxAge(0);
    }

    $ar_types = $ar_type_storage->loadMultiple($ar_type_ids);
    foreach ($ar_types as $ar_type) {
      $metadata->addCacheableDependency($ar_type);
    }

    return $ar_types;
  }

  /**
   * Returns all record types that should be accounted for a field access check.
   *
   * Additionally adds cacheability metadata to the given metadata object.
   *
   * @param \Drupal\Core\Entity\EntityInterface $subject
   *   The subject, which is mostly a user entity.
   * @param string $target_type_id
   *   The entity type ID of the target.
   * @param string $operation
   *   The requested entity operation.
   * @param \Drupal\Core\Cache\RefinableCacheableDependencyInterface|null $metadata
   *   (optional) The object that holds collected cacheability metadata.
   * @param bool $quick_check
   *   (optional) By default, a quick check is performed to see whether it
   *   makes sense to even look for access record types.
   *
   * @return \Drupal\access_records\AccessRecordTypeInterface[]
   *   The access record types. Can be empty.
   */
  public static function loadForFieldAccessCheck(EntityInterface $subject, string $target_type_id, string $operation, ?RefinableCacheableDependencyInterface $metadata = NULL, bool $quick_check = TRUE): array {
    return static::loadForAccessCheck($subject, $target_type_id, $operation, $metadata, $quick_check, TRUE);
  }

  /**
   * @inheritdoc
   */
  public function getFieldAccessEnabled(): bool {
    return $this->field_access_enabled;
  }

  /**
   * @inheritdoc
   */
  public function setFieldAccessEnabled(bool $enabled = TRUE): AccessRecordTypeInterface {
    $this->field_access_enabled = $enabled;
    return $this;
  }

  /**
   * @inheritdoc
   */
  public function getFieldAccessFieldsAllowed(): array {
    return $this->field_access_fields_allowed;
  }

  /**
   * @inheritdoc
   */
  public function setFieldAccessFieldsAllowed(array $field_names): AccessRecordTypeInterface {
    $this->field_access_fields_allowed = array_values($field_names);
    return $this;
  }

}
