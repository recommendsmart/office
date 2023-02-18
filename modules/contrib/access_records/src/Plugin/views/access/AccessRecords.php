<?php

namespace Drupal\access_records\Plugin\views\access;

use Drupal\access_records\AccessRecordQueryBuilder;
use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\access\AccessPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Access plugin that provides access on matching access records.
 *
 * @ingroup views_access_plugins
 *
 * @ViewsAccess(
 *   id = "access_records",
 *   title = @Translation("Access Records"),
 *   help = @Translation("Access will be granted to users having at least one matching access record.")
 * )
 */
class AccessRecords extends AccessPluginBase implements CacheableDependencyInterface {

  /**
   * {@inheritdoc}
   */
  protected $usesOptions = TRUE;

  /**
   * The query builder.
   *
   * @var \Drupal\access_records\AccessRecordQueryBuilder
   */
  protected AccessRecordQueryBuilder $queryBuilder;

  /**
   * The access record type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected EntityStorageInterface $typeStorage;

  /**
   * Constructs a new AccessRecords object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\access_records\AccessRecordQueryBuilder $query_builder
   *   The query builder.
   * @param \Drupal\Core\Entity\EntityStorageInterface $ar_type_storage
   *   The access record type storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccessRecordQueryBuilder $query_builder, EntityStorageInterface $ar_type_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->queryBuilder = $query_builder;
    $this->typeStorage = $ar_type_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('access_records.query_builder'),
      $container->get('entity_type.manager')->getStorage('access_record_type')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account) {
    $matched_ar_types = [];
    $ar_type_ids = array_filter($this->options['ar_types']);
    if ($ar_type_ids && ($user = User::load($account->id()))) {
      foreach ($user->getRoles() as $rid) {
        if ($role = Role::load($rid)) {
          if ($role->isAdmin()) {
            return TRUE;
          }
        }
      }

      foreach ($this->typeStorage->loadMultiple($ar_type_ids) as $ar_type) {
        $query = $this->queryBuilder->queryByType($ar_type, $user);
        if (count($query->range(0, 1)->execute()) > 0) {
          $matched_ar_types[$ar_type->id()] = $ar_type;
        }
      }
    }
    return !empty($matched_ar_types);
  }

  /**
   * {@inheritdoc}
   */
  public function alterRouteDefinition(Route $route) {
    if ($this->options['ar_types']) {
      $route->setRequirement('_access_record', (string) implode('+', $this->options['ar_types']));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    $count = count($this->options['ar_types']);
    if ($count < 1) {
      return $this->t('No access record type(s) selected');
    }
    elseif ($count > 1) {
      return $this->t('Multiple access record types');
    }
    else {
      $id = reset($this->options['ar_types']);
      $ar_type = $this->typeStorage->load($id);
      return $ar_type ? $ar_type->label() : NULL;
    }
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['ar_types'] = ['default' => []];

    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $options = [];
    $base_entity_type_id = $this->view->getBaseEntityType() ? $this->view->getBaseEntityType()->id() : NULL;
    /** @var \Drupal\access_records\AccessRecordTypeInterface $ar_type */
    foreach ($this->typeStorage->loadMultiple() as $ar_type) {
      if (!in_array('view', $ar_type->getOperations(), TRUE)) {
        continue;
      }
      if (isset($base_entity_type_id) && ($base_entity_type_id !== $ar_type->getTargetTypeId())) {
        continue;
      }

      $options[$ar_type->id()] = Html::escape((string) $ar_type->label());
    }
    $form['ar_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Access record types'),
      '#default_value' => $this->options['ar_types'],
      '#options' => $options,
      '#description' => $this->t('Only users having at least one matching record of the selected types will be able to access this display. Only access record types allowing the operation "view" are being listed here.'),
    ];
  }

  public function validateOptionsForm(&$form, FormStateInterface $form_state) {
    $ar_types = $form_state->getValue(['access_options', 'ar_types']);
    $ar_types = array_filter($ar_types);

    if (!$ar_types) {
      $form_state->setError($form['ar_types'], $this->t('You must select at least one access record type.'));
    }

    $form_state->setValue(['access_options', 'ar_types'], $ar_types);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach (array_keys($this->options['ar_types']) as $ar_type_id) {
      if ($ar_type = $this->typeStorage->load($ar_type_id)) {
        $dependencies[$ar_type->getConfigDependencyKey()][] = $ar_type->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return ['user'];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $tags = ['config:access_record_type_list'];
    foreach (array_keys($this->options['ar_types']) as $ar_type_id) {
      $tags[] = 'access_record_list:' . $ar_type_id;
    }
    return $tags;
  }

}
