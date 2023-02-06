<?php

namespace Drupal\access_records\Plugin\views\field;

use Drupal\access_records\AccessRecordInterface;
use Drupal\access_records\AccessRecordQueryBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field handler to display all subject IDs.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("access_record_subject_id")
 */
class AccessRecordSubjectId extends FieldPluginBase {

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
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->accessRecordQueryBuilder = $container->get('access_records.query_builder');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['format'] = ['default' => 'id'];
    $options['link'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['format'] = [
      '#type' => 'select',
      '#title' => $this->t('Format'),
      '#options' => [
        'id' => $this->t('ID'),
        'label' => $this->t('Label'),
      ],
      '#default_value' => $this->options['format'],
    ];
    $form['link'] = [
      '#title' => $this->t('Link to the subject page'),
      '#type' => 'checkbox',
      '#default_value' => !empty($this->options['link']),
    ];

    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function query($use_groupby = FALSE) {
    $this->ensureMyTable();
    $ar_entity_type = $this->entityTypeManager->getDefinition('access_record');
    $type_storage = $this->entityTypeManager->getStorage('access_record_type');

    $query_options = ['join_targets' => FALSE];
    $queries = [];
    /** @var \Drupal\access_records\AccessRecordTypeInterface $ar_type */
    foreach ($type_storage->loadMultiple() as $ar_type) {
      if (!$ar_type->status()) {
        continue;
      }

      if ($query = $this->accessRecordQueryBuilder->selectByType($ar_type, NULL, 'view', $query_options)) {
        $queries[$ar_type->id()] = $query;
      }
    }
    if (empty($queries)) {
      // Use a query whose result is always empty. It is safe to use the ar_id
      // for subject and target too, as we never get a row from here.
      $query = \Drupal::database()->select($ar_entity_type->getDataTable(), 'id')
        ->alwaysFalse(TRUE);
      $query->addField('id', 'ar_id', 'ar_id');
      $query->addField('id', 'ar_id', 'subject_id');
      $query->addField('id', 'ar_id', 'target_id');
      $queries[] = $query;
    }

    $query = array_shift($queries);
    /** @var \Drupal\Core\Database\Query\SelectInterface $union */
    foreach ($queries as $union) {
      $query->union($union);
    }

    $def = $this->definition;
    $def['table formula'] = $query;
    $def['table'] = $ar_entity_type->getDataTable();
    $def['field'] = 'ar_id';
    $def['left_table'] = $this->tableAlias;
    $def['left_field'] = 'ar_id';
    $def['type'] = 'LEFT';
    $def['adjusted'] = TRUE;

    $alias = $def['left_table'] . '_subjects';

    /** @var \Drupal\views\Plugin\views\query\Sql $view_query */
    $view_query = $this->query;
    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $def);
    $this->alias = $view_query->addRelationship($alias, $join, $ar_entity_type->getDataTable(), $this->relationship);
    $this->field_alias = $view_query->addField($alias, 'subject_id', 'ar_subject_id');
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $ar = $values->_entity ?? NULL;
    if (!($ar instanceof AccessRecordInterface)) {
      return parent::render($values);
    }

    $entity_type_id = $ar->getType()->getSubjectTypeId();
    $id = $this->getValue($values);
    if (!($entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id))) {
      return $this->sanitizeValue('');
    }

    if ($this->options['format'] === 'label') {
      $value = $this->sanitizeValue($entity->label());
    }
    else {
      $value = $this->sanitizeValue($entity->id());
    }

    if (!empty($this->options['link']) && $entity->hasLinkTemplate('canonical')) {
      return $entity->toLink($value, 'canonical')->toString();
    }
    return $value;
  }

}
