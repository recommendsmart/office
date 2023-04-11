<?php

namespace Drupal\group_action\Plugin\Action;

use Drupal\Component\Uuid\Uuid;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Action\ConfigurableActionBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Token;
use Drupal\group\Entity\GroupInterface;
use Drupal\group_action\Compatibility;
use Drupal\user\Entity\Role;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Group-related actions.
 *
 * @internal
 *   This class is not meant to be used as public API.
 */
abstract class GroupActionBase extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Flag indicating whether the used version of Group is v2 or greater.
   *
   * @var bool|null
   */
  static protected ?bool $isV2 = NULL;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The plugin manager of group relation types.
   *
   * @var \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface|\Drupal\group\Plugin\GroupContentEnablerManagerInterface
   */
  protected $gcePluginManager;

  /**
   * The Token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * An instance of a Compatibility object.
   *
   * @var \Drupal\group_action\Compatibility
   */
  protected Compatibility $compatibility;

  /**
   * Constructs a new GroupActionBase plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\group\Plugin\Group\Relation\GroupRelationTypeManagerInterface|\Drupal\group\Plugin\GroupContentEnablerManagerInterface $gce_plugin_manager
   *   The plugin manager of group relation types.
   * @param \Drupal\Core\Utility\Token $token
   *   The Token service.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\group_action\Compatibility $compatibility
   *   An instance of a Compatibility object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, $gce_plugin_manager, Token $token, TranslationInterface $string_translation, EntityRepositoryInterface $entity_repository, AccountInterface $current_user, Compatibility $compatibility) {
    parent::__construct($configuration + ['values' => []], $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->gcePluginManager = $gce_plugin_manager;
    $this->token = $token;
    $this->stringTranslation = $string_translation;
    $this->entityRepository = $entity_repository;
    $this->currentUser = $current_user;
    $this->compatibility = $compatibility;
    if (is_string($this->configuration['values'])) {
      $this->configuration['values'] = $this->decodeValues($this->configuration['values']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    if (!isset(self::$isV2)) {
      self::$isV2 = $container->has('group_relation_type.manager');
    }
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      self::$isV2 ? $container->get('group_relation_type.manager') : $container->get('plugin.manager.group_content_enabler'),
      $container->get('token'),
      $container->get('string_translation'),
      $container->get('entity.repository'),
      $container->get('current_user'),
      new Compatibility($container->get('module_handler'))
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'operation' => '', // Either one of "create", "update" or "delete".
      'content_plugin' => '', // The group content plugin ID.
      'group_id' => '', // The group ID. Can be numerical or a UUID.
      'entity_id' => '', // The entity ID. Can be numerical or a UUID.
      'values' => '', // Raw field values. Must be resolved to a flat array.
      //'add_method' => '', // Only relevant for "create" operation.
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $default_config = $this->defaultConfiguration();
    if ($default_config['operation'] === '') {
      $form['operation'] = [
        '#type' => 'select',
        '#title' => $this->t('Operation'),
        '#options' => [
          'create' => $this->t('Add'),
          'update' => $this->t('Update'),
          'delete' => $this->t('Remove'),
        ],
        '#default_value' => $this->configuration['operation'] ?? 'create',
        '#required' => TRUE,
      ];
    }
    if ($default_config['content_plugin'] === '') {
      $derivative_id = $this->getDerivativeId();
      $plugin_options = [
        '_none' => $this->t('- Select -'),
      ];
      $dynamics = [];
      /** @var \Drupal\group\Plugin\Group\Relation\GroupRelationType $definition */
      foreach ($this->gcePluginManager->getDefinitions() as $id => $definition) {
        $entity_type_id = self::$isV2 ? $definition->getEntityTypeId() : ($definition['entity_type_id'] ?? NULL);
        if ($derivative_id && ($derivative_id !== $entity_type_id)) {
          continue;
        }
        $plugin_options[$id] = self::$isV2 ? $definition->getLabel() : $definition['label'];
        if (isset($entity_type_id)) {
          $id = self::$isV2 ? $definition->id() : $definition['id'];
          $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
          if (($entity_type->hasKey('bundle') || $entity_type->getBundleEntityType())) {
            $dynamics[$id] = $entity_type->getLabel() . ' (' . $this->t('dynamic') . ')';
          }
        }
      }
      $plugin_options = array_merge($plugin_options, $dynamics);
      $form['content_plugin'] = [
        '#type' => 'select',
        '#title' => $this->t('Type of group content'),
        '#options' => $plugin_options,
        '#default_value' => $this->configuration['content_plugin'] ?? '_none',
        '#required' => TRUE,
        '#empty_value' => '_none',
      ];
    }
    if ($default_config['group_id'] === '') {
      $form['group_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Group ID / UUID'),
        '#description' => $this->t('The numerical or universally unique ID of the group. This field supports tokens.'),
        '#default_value' => $this->configuration['group_id'] ?? '',
        '#required' => TRUE,
      ];
    }
    if ($default_config['entity_id'] === '') {
      $form['entity_id'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Entity ID / UUID'),
        '#description' => $this->t('The entity ID. Supports tokens. Leave blank to use the entity this action will operate on.'),
        '#default_value' => $this->configuration['entity_id'] ?? '',
      ];
    }
    if (($default_config['values'] === '') && ($default_config['operation'] !== 'delete')) {
      $values = $this->configuration['values'] ?? [];
      if (is_string($values)) {
        $values = $this->decodeValues($values);
      }
      $values_string = '';
      foreach ($values as $k => $vs) {
        if (!is_array($vs)) {
          $vs = [$vs];
          $values[$k] = $vs;
        }
        foreach ($vs as $v) {
          $values_string .= $k . ': ' . $v . "\n";
        }
      }
      $form['values'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Group content values'),
        '#default_value' => $values_string,
        '#description' => $this->t('A key-value list of raw field values to set for the Group content. Supports tokens. Set one value per line. Example:<em><br/>field_mynumber: 1<br/>group_roles: mygroup-myrole1<br/>group_roles: mygroup-myrole2</em>'),
      ];
    }
    if (($this->configuration['operation'] ?? 'create') === 'create') {
      $form['add_method'] = [
        '#type' => 'select',
        '#title' => $this->t('How to add'),
        '#options' => [
          'skip_existing' => $this->t('Only add when not yet added'),
          'always_add' => $this->t('Always add, no matter if already added'),
          'update_existing' => $this->t('Update if already added'),
        ],
        '#required' => TRUE,
        '#default_value' => $this->configuration['add_method'] ?? 'skip_existing',
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // @todo Validate raw values.
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $default_config = $this->defaultConfiguration();
    if ($default_config['operation'] === '') {
      $this->configuration['operation'] = $form_state->getValue('operation');
    }
    if ($default_config['group_id'] === '') {
      $this->configuration['group_id'] = $form_state->getValue('group_id');
    }
    if ($default_config['entity_id'] === '') {
      $this->configuration['entity_id'] = $form_state->getValue('entity_id');
    }
    if ($default_config['content_plugin'] === '') {
      $this->configuration['content_plugin'] = $form_state->getValue('content_plugin');
    }
    if (($default_config['values'] === '') && ($default_config['operation'] !== 'delete')) {
      $this->configuration['values'] = $this->decodeValues($form_state->getValue('values', ''));
    }
    if (($this->configuration['operation'] === 'create') && $form_state->hasValue('add_method')) {
      $this->configuration['add_method'] = $form_state->getValue('add_method');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (is_null($account)) {
      $account = $this->currentUser instanceof AccountProxyInterface ? $this->currentUser->getAccount() : $this->currentUser;
    }
    $entity = $this->loadEntity($object);
    $entity_type = $entity ? $entity->getEntityType() : NULL;
    $group = $this->loadGroup($entity);
    $content_plugin_id = $this->configuration['content_plugin'];
    if ($entity_type && ($entity_type->hasKey('bundle') || $entity_type->getBundleEntityType()) && (strpos($content_plugin_id, ':') === FALSE) && $this->gcePluginManager->hasDefinition($content_plugin_id . ':' . $entity->bundle())) {
      $content_plugin_id = $content_plugin_id . ':' . $entity->bundle();
    }
    if ($group && in_array($content_plugin_id, $this->gcePluginManager->getInstalledIds($group->getGroupType()))) {
      $operation = $this->configuration['operation'];
      if (self::$isV2) {
        $permission_provider = $this->gcePluginManager->getPermissionProvider($content_plugin_id);
        $result = NULL;
        if ($permission = $permission_provider->getPermission($operation, 'relationship', 'any')) {
          $result = AccessResult::allowedIf($group->hasPermission($permission, $account))
            ->addCacheableDependency($group)
            ->addCacheContexts(['user']);
        }
        if ((!$result || !$result->isAllowed()) && ($permission = $permission_provider->getPermission($operation, 'relationship', 'own'))) {
          $result = AccessResult::allowedIf($group->hasPermission($permission, $account))
            ->addCacheableDependency($group)
            ->addCacheContexts(['user']);
        }
        if (!$result) {
          $result = AccessResult::forbidden("The requested content plugin does not provide any permissions.")
            ->addCacheableDependency($group);
        }
        if (!$result->isAllowed() && $account->isAuthenticated()) {
          $is_admin = ((int) $account->id() === 1) || !empty(array_filter($account->getRoles(TRUE), function ($rid) {
            $role = Role::load($rid);
            return $role && $role->isAdmin();
          }));
          if ($is_admin) {
            $result = AccessResult::allowed()
              ->addCacheableDependency($group)
              ->addCacheContexts(['user']);
          }
        }
        if (!$result->isAllowed() && ($result instanceof AccessResultReasonInterface)) {
          $result->setReason("The current user has no permission to perform the group operation.");
        }
      }
      else {
        $result = AccessResult::allowedIf($group->hasPermission("$operation $content_plugin_id content", $account))
          ->addCacheableDependency($group);
      }
    }
    else {
      $result = $group ? AccessResult::forbidden("The requested content plugin is not installed.") : AccessResult::forbidden("Cannot operate on a non-existing group.");
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $entity = $this->loadEntity($entity);
    if (!($entity instanceof EntityInterface) || !($group = $this->loadGroup($entity))) {
      return;
    }
    $entity_type = $entity->getEntityType();
    $content_plugin_id = $this->configuration['content_plugin'];
    if (($entity_type->hasKey('bundle') || $entity_type->getBundleEntityType()) && (strpos($content_plugin_id, ':') === FALSE) && $this->gcePluginManager->hasDefinition($content_plugin_id . ':' . $entity->bundle())) {
      $content_plugin_id = $content_plugin_id . ':' . $entity->bundle();
    }
    if (!in_array($content_plugin_id, $this->gcePluginManager->getInstalledIds($group->getGroupType()))) {
      return;
    }
    $content_plugin_definition = $this->gcePluginManager->getDefinition($content_plugin_id);
    $entity_type_id = self::$isV2 ? $content_plugin_definition->getEntityTypeId() : ($content_plugin_definition['entity_type_id'] ?? NULL);
    if ($entity_type_id !== $entity->getEntityTypeId()) {
      return;
    }
    $entity_bundle = self::$isV2 ? $content_plugin_definition->getEntityBundle() : ($content_plugin_definition['entity_bundle'] ?? FALSE);
    if (($entity_bundle !== FALSE) && ($entity_bundle !== $entity->bundle())) {
      return;
    }
    $operation = $this->configuration['operation'];

    $values = $this->configuration['values'] ?? [];
    $values_contain_token = FALSE;
    foreach ($values as $k => $vs) {
      if (!is_array($vs)) {
        $vs = [$vs];
        $values[$k] = $vs;
      }
      foreach ($vs as $v) {
        if (!empty($v) && strpos($v, '[') !== FALSE) {
          $values_contain_token = TRUE;
          break 2;
        }
      }
    }
    if ($values_contain_token) {
      $token_data = [];
      $token_data[$entity->getEntityTypeId()] = $entity;
      if (!($entity instanceof GroupInterface)) {
        $token_data['group'] = $group;
      }
      $token_options = ['clear' => TRUE];
      foreach ($values as $k => $vs) {
        if (!is_array($vs)) {
          $vs = [$vs];
          $values[$k] = $vs;
        }
        foreach ($vs as $i => $v) {
          $v = trim((string) $this->token->replace($v, $token_data, $token_options));
          if ($v === '') {
            unset($values[$k][$i]);
          }
          else {
            $values[$k][$i] = $v;
          }
        }
      }
    }
    foreach ($values as $k => $vs) {
      if (is_array($vs) && (count($vs) === 1)) {
        $values[$k] = reset($vs);
      }
    }
    unset($k, $vs);

    $this->compatibility->beforeOperation();
    $this->executeOperation($operation, $group, $entity, $content_plugin_id, $values);
    $this->compatibility->afterOperation();
  }

  /**
   * Executes the requested operation on the given group.
   *
   * @param string &$operation
   *   The requested operation, e.g. "update".
   * @param \Drupal\group\Entity\GroupInterface $group
   *   The group.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity this action is executed on.
   * @param string &$content_plugin_id
   *   The determined content plugin ID to use.
   * @param array &$values
   *   Configured values for this operation.
   */
  protected function executeOperation(string &$operation, GroupInterface $group, EntityInterface $entity, string &$content_plugin_id, array &$values): void {
    if ($operation === 'create') {
      $may_add = TRUE;
      $add_method = $this->configuration['add_method'] ?? '';
      if ($add_method === '') {
        $add_method = 'skip_existing';
      }
      if (in_array($add_method, ['skip_existing', 'update_existing'])) {
        $may_add = self::$isV2 ? empty($this->getRelationshipsByEntityAndGroup($entity, $group, $content_plugin_id)) : empty($group->getContent($content_plugin_id, ['entity_id' => $entity->id()]));
      }
      if ($may_add) {
        if (self::$isV2) {
          $group->addRelationship($entity, $content_plugin_id, $values);
        }
        else {
          $group->addContent($entity, $content_plugin_id, $values);
        }
      }
      elseif ($add_method === 'update_existing') {
        $operation = 'update';
      }
    }
    if (($operation === 'delete') && !$entity->isNew()) {
      $group_contents = self::$isV2 ? $this->getRelationshipsByEntityAndGroup($entity, $group, $content_plugin_id) : $group->getContent($content_plugin_id, ['entity_id' => $entity->id()]);
      foreach ($group_contents as $group_content) {
        $group_content->delete();
      }
    }
    if (($operation === 'update') && !$entity->isNew()) {
      $group_contents = self::$isV2 ? $this->getRelationshipsByEntityAndGroup($entity, $group, $content_plugin_id) : $group->getContent($content_plugin_id, ['entity_id' => $entity->id()]);
      foreach ($group_contents as $group_content) {
        $need_save = FALSE;
        foreach ($values as $k => $vs) {
          if ($group_content->hasField($k) && ($group_content->get($k)->getValue() !== $vs)) {
            $group_content->get($k)->setValue($vs);
            $need_save = TRUE;
          }
        }
        if ($need_save) {
          $group_content->save();
        }
      }
    }
  }

  /**
   * Loads the group using the plugin configuration.
   *
   * @param mixed $entity
   *   (Optional) The entity upon the action is to be executed.
   *
   * @return \Drupal\group\Entity\GroupInterface|null
   *   The loaded group, or NULL if no group could be loaded.
   */
  protected function loadGroup($entity = NULL): ?GroupInterface {
    $group_id = $this->configuration['group_id'] ?? '';
    if (!empty($group_id) && strpos($group_id, '[') !== FALSE) {
      $token_data = [];
      if ($entity instanceof EntityInterface) {
        $token_data[$entity->getEntityTypeId()] = $entity;
      }
      $token_options = ['clear' => TRUE];
      $group_id = (string) $this->token->replace($group_id, $token_data, $token_options);
    }
    $group_id = trim($group_id);
    if ($group_id === '') {
      return NULL;
    }
    if (ctype_digit($group_id)) {
      return $this->entityTypeManager->getStorage('group')->load($group_id);
    }
    if (Uuid::isValid($group_id)) {
      return $this->entityRepository->loadEntityByUuid('group', $group_id);
    }
    return NULL;
  }

  /**
   * Load the entity using the plugin configuration.
   *
   * @param mixed $entity
   *   (Optional) The entity upon the action is to be executed.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity or NULL if the entity could not be resolved.
   */
  protected function loadEntity($entity = NULL): ?EntityInterface {
    $entity_id = $this->configuration['entity_id'] ?? '';
    if (!empty($entity_id)) {
      $token_data = [];
      if ($entity instanceof EntityInterface) {
        $token_data[$entity->getEntityTypeId()] = $entity;
      }
      $token_options = ['clear' => TRUE];
      $entity_id = (string) $this->token->replace($entity_id, $token_data, $token_options);
    }
    $entity_id = trim($entity_id);
    if ($entity_id === '') {
      return $entity;
    }
    $content_plugin_id = $this->configuration['content_plugin'];
    if ($this->gcePluginManager->hasDefinition($content_plugin_id)) {
      $content_plugin_definition = $this->gcePluginManager->getDefinition($content_plugin_id);
    }
    elseif (strpos($content_plugin_id, ':') === FALSE) {
      foreach ($this->gcePluginManager->getDefinitions() as $definition) {
        if ((self::$isV2 ? $definition->id() : $definition['id']) === $content_plugin_id) {
          $content_plugin_definition = $definition;
          break;
        }
      }
    }
    $entity_type_id = self::$isV2 ? $content_plugin_definition->getEntityTypeId() : ($content_plugin_definition['entity_type_id'] ?? NULL);
    if (!isset($entity_type_id)) {
      return NULL;
    }
    if (Uuid::isValid($entity_id)) {
      return $this->entityRepository->loadEntityByUuid($entity_type_id, $entity_id);
    }
    return $this->entityTypeManager->getStorage($entity_type_id)->load($entity_id);
  }

  protected function decodeValues(string $values_string): array {
    $values = [];
    $tok = strtok($values_string, "\n");
    while ($tok !== false) {
      [$k, $v] = array_merge(explode(':', $tok, 2), ['']);
      if (trim($k) !== '') {
        $values[trim($k)][] = trim($v);
      }
      $tok = strtok("\n");
    }
    return $values;
  }

  /**
   * @todo Remove once the below issue got fixed.
   * @see https://www.drupal.org/project/group/issues/3310605
   */
  protected function getRelationshipsByEntityAndGroup(EntityInterface $entity, GroupInterface $group, string $content_plugin_id) {
    return array_filter($group->getRelationshipsByEntity($entity, $content_plugin_id), static function ($relationship) use ($group) {
      /** @var \Drupal\group\Entity\GroupRelationshipInterface $relationship */
      return (((string) $relationship->getGroupId()) === ((string) $group->id()));
    });
  }

}
