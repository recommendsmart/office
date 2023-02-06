<?php

namespace Drupal\access_records\Plugin\Action;

use Drupal\access_records\AccessRecordInterface;
use Drupal\access_records\AccessRecordTypeInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for actions related to access records.
 */
abstract class AccessRecordsActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Token replacement service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The lock service.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = (new static($configuration, $plugin_id, $plugin_definition))
      ->setEntityTypeManager($container->get('entity_type.manager'))
      ->setToken($container->get('token'))
      ->setLockBackend($container->get('lock'))
      ->setModuleHandler($container->get('module_handler'));
    $instance->setStringTranslation($container->get('string_translation'));
    return $instance;
  }

  /**
   * Get the access record type.
   *
   * @return \Drupal\access_records\AccessRecordTypeInterface
   *   The access record type.
   */
  public function getAccessRecordType(): AccessRecordTypeInterface {
    return $this->entityTypeManager
      ->getStorage('access_record_type')
      ->load($this->getDerivativeId());
  }

  /**
   * Update the access record, that matches up with the plugin configuration.
   *
   * @param array $values
   *   The values to update, keyed by field name.
   * @param array $context
   *   (optional) Context data that can be passed to Token replacement.
   *
   * @return \Drupal\access_records\AccessRecordInterface|null
   *   The updated access record. New records are automatically being inserted.
   *   When the values were not changed, the access record remains unchanged.
   *
   * @throws \InvalidArgumentException
   *   - When no subject ID or no target ID is provided by the plugin config.
   *   - When an invalid field name is given in the $values argument array.
   */
  public function updateAccessRecord(array $values, array $context = []): ?AccessRecordInterface {
    $subject_id = $this->getSubjectId($context);
    $target_id = $this->getTargetId($context);

    if ($subject_id === '' || $target_id === '') {
      throw new \InvalidArgumentException("The plugin configuration does not provide a subject ID and target ID.");
    }

    $lock_name = 'access_records:action:' . $subject_id . ':' . $target_id;
    if (!$this->lock->acquire($lock_name)) {
      usleep(50000);
      return $this->updateAccessRecord($values, $context);
    }

    $ar = $this->getAccessRecord($context + [
      'subject_id' => $subject_id,
      'target_id' => $target_id,
    ]);
    $needs_save = $ar->isNew();

    /** @var \Drupal\access_records\AccessRecordInterface $ar */
    foreach ($values as $k => $v) {
      if ($ar->get($k)->getValue() !== $v) {
        $ar->get($k)->setValue($v);
        $needs_save = TRUE;
      }
    }

    if ($needs_save) {
      $ar_type = $this->getAccessRecordType();
      $storage = $this->entityTypeManager->getStorage('access_record');
      if (!$ar->isNew() && $ar_type->shouldCreateNewRevision()) {
        $ar->setNewRevision();
      }
      $storage->save($ar);
    }

    $this->lock->release($lock_name);

    return $ar;
  }

  /**
   * Loads the access record, that matches up with the plugin configuration.
   *
   * @param array $values
   *   The values to update, keyed by field name.
   * @param array $context
   *   (optional) Context data that can be passed to Token replacement.
   *
   * @return \Drupal\access_records\AccessRecordInterface|null
   *   The access record. May be a new entity.
   *
   * @throws \InvalidArgumentException
   *   - When no subject ID or no target ID is provided by the plugin config.
   */
  public function getAccessRecord(array $context = []): AccessRecordInterface {
    $ar_type = $this->getAccessRecordType();
    $subject_type = $ar_type->getSubjectType();
    $target_type = $ar_type->getTargetType();

    $storage = $this->entityTypeManager->getStorage('access_record');

    $subject_field_names = $ar_type->getSubjectFieldNames();
    $target_field_names = $ar_type->getTargetFieldNames();
    $ar_subject_id_field_name = array_search($subject_type->getKey('id'), $subject_field_names, TRUE);
    $ar_target_id_field_name = array_search($target_type->getKey('id'), $target_field_names, TRUE);

    $subject_id = $this->getSubjectId($context);
    $target_id = $this->getTargetId($context);

    if ($subject_id === '' || $target_id === '') {
      throw new \InvalidArgumentException("The plugin configuration does not provide a subject ID and target ID.");
    }

    $result = $storage
      ->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->condition($ar_subject_id_field_name, $subject_id)
      ->condition($ar_target_id_field_name, $target_id)
      ->execute();
    if (!empty($result)) {
      $ar = $storage->load(reset($result));
    }
    else {
      $ar = $storage->create([
        'ar_type' => $ar_type->id(),
        'ar_enabled' => FALSE,
        $ar_subject_id_field_name => $subject_id,
        $ar_target_id_field_name => $target_id,
      ]);
    }

    return $ar;
  }

  /**
   * Get the configured subject ID.
   *
   * @param array $context
   *   (optional) Context data that can be passed to Token replacement.
   *
   * @return string
   *   The subject ID.
   */
  public function getSubjectId($context = []): string {
    return $this->getScopeId('subject', $context);
  }

  /**
   * Get the configured target ID.
   *
   * @param array $context
   *   (optional) Context data that can be passed to Token replacement.
   *
   * @return string
   *   The target ID.
   */
  public function getTargetId($context = []): string {
    return $this->getScopeId('target', $context);
  }

  /**
   * Helper method to the configured ID of the given scope.
   *
   * @param string $scope
   *   The scope, either "subject" or "target".
   * @param array $context
   *   (optional) Context data that can be passed to Token replacement.
   *
   * @return string
   *   The ID of the given scope.
   */
  protected function getScopeId(string $scope, $context = []): string {
    $scope_id_name = $scope . '_id';
    if (isset($context[$scope_id_name])) {
      return $context[$scope_id_name];
    }

    $id = trim((string) ($this->configuration[$scope_id_name] ?? ''));
    if ($id !== '') {
      $id = trim((string) $this->token->replace($id, $this->buildTokenData($context), ['clear' => TRUE]));
    }
    else {
      $scope_type_id = $this->getAccessRecordType()->getTargetTypeId();
      foreach ($context as $k => $v) {
        if ($scope === $k) {
          $id = $v instanceof EntityInterface ? $v->id() : (is_scalar($v) ? $v : '');
          break;
        }
        if ($v instanceof EntityInterface) {
          if ($v->getEntityTypeId() === $scope_type_id) {
            $id = $v->id();
            break;
          }
        }
      }
    }

    return (string) $id;
  }

  /**
   * Set the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $etm): AccessRecordsActionBase {
    $this->entityTypeManager = $etm;
    return $this;
  }

  /**
   * Set the Token replacement service.
   *
   * @param \Drupal\Core\Utility\Token $token
   *   The Token replacement service.
   *
   * @return $this
   */
  public function setToken(Token $token): AccessRecordsActionBase {
    $this->token = $token;
    return $this;
  }

  /**
   * Set the lock backend.
   *
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   *
   * @return $this
   */
  public function setLockBackend(LockBackendInterface $lock): AccessRecordsActionBase {
    $this->lock = $lock;
    return $this;
  }

  /**
   * Set the module handler.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler): AccessRecordsActionBase {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();
    $ar_type = $this->getAccessRecordType();
    $dependencies[$ar_type->getConfigDependencyKey()][] = $ar_type->getConfigDependencyName();
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'subject_id' => '',
      'target_id' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $ar_type = $this->getAccessRecordType();
    $subject_type = $ar_type->getSubjectType();
    $target_type = $ar_type->getTargetType();
    $form['subject_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID of @subject (subject)', [
        '@subject' => $subject_type->getLabel(),
      ]),
      '#description' => $this->t('The primary identifier of the @type. This field supports tokens.', [
        '@type' => $subject_type->getLabel(),
      ]),
      '#required' => TRUE,
      '#weight' => 10,
    ];
    $form['target_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('ID of @target (target)', [
        '@target' => $target_type->getLabel(),
      ]),
      '#description' => $this->t('The primary identifier of the @type. This field supports tokens.', [
        '@type' => $target_type->getLabel(),
      ]),
      '#required' => FALSE,
      '#weight' => 20,
    ];
    $form['target_id']['#description'] .= ' ' . $this->t('Leave this field empty to use the @type item this action operates on.', [
      '@type' => $target_type->getLabel(),
    ]);

    $form['token_browser'] = [
      '#type' => 'container',
      '#weight' => 30,
    ];
    if ($this->moduleHandler->moduleExists('token')) {
      $form['token_browser']['token_link'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [
          $subject_type->get('token_type') ?: $subject_type->id(),
          $target_type->get('token_type') ?: $target_type->id()
        ],
        '#dialog' => TRUE,
      ];
    }
    else {
      $form['token_browser']['note']['#markup'] = $this->t('To get a list of available tokens, install the <a target="_blank" rel="noreferrer noopener" href=":drupal-token" target="blank">contrib Token</a> module.', [':drupal-token' => 'https://www.drupal.org/project/token']);
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['subject_id'] = $form_state->getValue('subject_id', $this->configuration['subject_id'] ?? '');
    $this->configuration['target_id'] = $form_state->getValue('target_id', $this->configuration['target_id'] ?? '');
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\Core\Entity\EntityAccessControlHandlerInterface $access_handler */
    $access_handler = $this->entityTypeManager->getHandler('access_record', 'access');
    $ar = $this->getAccessRecord([$object]);

    if ($ar->isNew()) {
      $result = $access_handler->createAccess($this->getDerivativeId(), $account, [
        'entity_type_id' => 'access_record',
      ], TRUE);
    }
    else {
      $result = $ar->access('update', $account, TRUE);
    }

    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($object = NULL) {
    $context = [];
    if (isset($object)) {
      $context[] = $object;
    }
    $this->updateAccessRecord($this->getUpdateValues($context), $context);
  }

  /**
   * Helper method to build up data to be passed to Token replacement.
   *
   * @param array $context
   *   (optional) Available context that may be used as Token data.
   *
   * @return array
   *   The token data.
   */
  protected function buildTokenData(array $context = []): array {
    $data = [];
    foreach ($context as $k => $v) {
      $data[$k] = $v;
      if ($v instanceof EntityInterface) {
        $token_type = $v->getEntityType()->get('token_type') ?: $v->getEntityTypeId();
        $data[$token_type] = $v;
      }
    }
    return $data;
  }

  /**
   * Get the values to update on the access record.
   *
   * @param array $context
   *   (optional) Available context that may be used as Token data.
   */
  abstract public function getUpdateValues(array $context = []): array;

}
