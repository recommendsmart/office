<?php

namespace Drupal\entity_taxonomy\Plugin\views\relationship;

use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_taxonomy\VocabularyStorageInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Relationship handler to return the entity_taxonomy terms of nodes.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("entity_term_data")
 */
class EntityTermData extends RelationshipPluginBase {

  /**
   * The vocabulary storage.
   *
   * @var \Drupal\entity_taxonomy\VocabularyStorageInterface
   */
  protected $vocabularyStorage;

  /**
   * Constructs a NodeTermData object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\entity_taxonomy\VocabularyStorageInterface $vocabulary_storage
   *   The vocabulary storage.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, VocabularyStorageInterface $vocabulary_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->vocabularyStorage = $vocabulary_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('entity_taxonomy_vocabulary')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    // @todo Remove the legacy code.
    // Convert legacy vids option to machine name vocabularies.
    if (!empty($this->options['vids'])) {
      $vocabularies = entity_taxonomy_vocabulary_get_names();
      foreach ($this->options['vids'] as $vid) {
        if (isset($vocabularies[$vid], $vocabularies[$vid]->machine_name)) {
          $this->options['vocabularies'][$vocabularies[$vid]->machine_name] = $vocabularies[$vid]->machine_name;
        }
      }
    }
  }

  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['vids'] = ['default' => []];
    return $options;
  }

  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    $options = [];
    foreach ($vocabularies as $voc) {
      $options[$voc->id()] = $voc->label();
    }

    $form['vids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#options' => $options,
      '#default_value' => $this->options['vids'],
      '#description' => $this->t('Choose which vocabularies you wish to relate. Remember that every term found will create a new record, so this relationship is best used on just one vocabulary that has only one term per node.'),
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitOptionsForm(&$form, FormStateInterface $form_state) {
    // Transform the #type = checkboxes value to a numerically indexed array,
    // because the config schema expects a sequence, not a mapping.
    $vids = $form_state->getValue(['options', 'vids']);
    $form_state->setValue(['options', 'vids'], array_values(array_filter($vids)));
  }

  /**
   * Called to implement a relationship in a query.
   */
  public function query() {
    $this->ensureMyTable();
    $def = $this->definition;
    $def['table'] = 'entity_taxonomy_term_field_data';
    if (!array_filter($this->options['vids'])) {
      $entity_taxonomy_index = $this->query->addTable('entity_taxonomy_index', $this->relationship);
      $def['left_table'] = $entity_taxonomy_index;
      $def['left_field'] = 'tid';
      $def['field'] = 'tid';
      $def['extra'] = [
        0 => [
          'field' => 'entity_type',
          'value' => 'user',
        ],
      ];
      $def['type'] = empty($this->options['required']) ? 'LEFT' : 'INNER';
    }
    else {
      // If vocabularies are supplied join a subselect instead
      $def['left_table'] = $this->tableAlias;
      $def['left_field'] = 'nid';
      $def['field'] = 'nid';
      $def['type'] = empty($this->options['required']) ? 'LEFT' : 'INNER';
      $def['adjusted'] = TRUE;

      $query = Database::getConnection()->select('entity_taxonomy_term_field_data', 'td');
      $query->addJoin($def['type'], 'entity_taxonomy_index', 'tn', 'tn.tid = td.tid');
      $query->condition('td.vid', array_filter($this->options['vids']), 'IN');
      
      if (empty($this->query->options['disable_sql_rewrite'])) {
        $query->addTag('entity_taxonomy_term_access');
      }
      $query->fields('td');
      $query->fields('tn', ['entity_id']);
      $def['table formula'] = $query;
    }

    $join = \Drupal::service('plugin.manager.views.join')->createInstance('standard', $def);

    // use a short alias for this:
    $alias = $def['table'] . '_' . $this->table;

    $this->alias = $this->query->addRelationship($alias, $join, 'entity_taxonomy_term_field_data', $this->relationship);
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    $dependencies = parent::calculateDependencies();

    foreach ($this->options['vids'] as $vocabulary_id) {
      if ($vocabulary = $this->vocabularyStorage->load($vocabulary_id)) {
        $dependencies[$vocabulary->getConfigDependencyKey()][] = $vocabulary->getConfigDependencyName();
      }
    }

    return $dependencies;
  }

}
