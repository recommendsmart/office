<?php

namespace Drupal\flow_ui\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\flow\Flow;
use Drupal\flow\FlowCompatibility;
use Drupal\flow\FlowTaskMode;
use Drupal\flow\Plugin\flow\Subject\Reference;
use Drupal\flow\Plugin\FlowQualifierManager;
use Drupal\flow\Plugin\FlowSubjectManager;
use Drupal\flow\Plugin\FlowTaskManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for a Flow configuration.
 */
class FlowForm extends EntityForm {

  /**
   * The Flow task manager.
   *
   * @var \Drupal\flow\Plugin\FlowTaskManager
   */
  protected FlowTaskManager $taskManager;

  /**
   * The Flow subject manager.
   *
   * @var \Drupal\flow\Plugin\FlowSubjectManager
   */
  protected FlowSubjectManager $subjectManager;

  /**
   * The Flow qualifier manager.
   *
   * @var \Drupal\flow\Plugin\FlowQualifierManager
   */
  protected FlowQualifierManager $qualifierManager;

  /**
   * Constructs a new FlowForm.
   *
   * @param \Drupal\flow\Plugin\FlowTaskManager $task_manager
   *   The Flow task manager.
   * @param \Drupal\flow\Plugin\FlowSubjectManager $subject_manager
   *   The Flow subject manager.
   * @param \Drupal\flow\Plugin\FlowQualifierManager $qualifier_manager
   *   The Flow qualifier manager.
   */
  public function __construct(FlowTaskManager $task_manager, FlowSubjectManager $subject_manager, FlowQualifierManager $qualifier_manager) {
    $this->taskManager = $task_manager;
    $this->subjectManager = $subject_manager;
    $this->qualifierManager = $qualifier_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static($container->get('plugin.manager.flow.task'), $container->get('plugin.manager.flow.subject'), $container->get('plugin.manager.flow.qualifier'));
    $instance->setEntityTypeManager($container->get('entity_type.manager'));
    $instance->setModuleHandler($container->get('module_handler'));
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\flow\Entity\FlowInterface $flow */
    $flow = $this->entity;
    $target_type = $this->entityTypeManager->getDefinition($flow->getTargetEntityTypeId());
    $bundle_type_id = $target_type->getBundleEntityType() ?: 'bundle';

    if (!$flow->getStatus()) {
      $this->messenger()->addWarning($this->t('This configuration is not active and thus will not get applied. Please contact your site administrator if that is not an expected status.'));
    }

    $form = parent::buildForm($form, $form_state);
    $weight = 0;
    $custom = $flow->isCustom() ? $flow->get('custom') : FALSE;

    if ($custom) {
      $task_subjects = $flow->getSubjects();
      $form['custom'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Qualifiers'),
        '#weight' => $weight++,
      ];
      $qualifiers = $flow->getQualifiers();
      $qualifying_subjects = $flow->getQualifyingSubjects();
      if ($qualifiers->count()) {
        $form['custom']['qualifiers'] = [
          '#attributes' => ['id' => Html::getUniqueId('flow-qualifiers')],
          '#type' => 'table',
          '#parents' => ['custom'],
          '#header' => [
            $this->t('Qualifier'),
            $this->t('Subject'),
            $this->t('Operations'),
          ],
          '#weight' => 10,
        ];
        /** @var \Drupal\flow\Plugin\FlowQualifierInterface $qualifier */
        foreach ($qualifiers as $i => $qualifier) {
          $subject = $qualifying_subjects->get($i);
          $form['custom']['qualifiers'][$i]['qualifier'] = [
            '#type' => 'markup',
            '#markup' => $qualifier->getPluginDefinition()['label'],
            '#weight' => 10,
          ];
          $form['custom']['qualifiers'][$i]['subject'] = [
            '#type' => 'markup',
            '#markup' => $subject->getPluginDefinition()['label'],
            '#weight' => 20,
          ];
          $operations = [];
          if ($flow->access('update')) {
            $operations['edit'] = [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute("flow.qualifier.{$target_type->id()}.edit", [
                'entity_type_id' => $target_type->id(),
                $bundle_type_id => $flow->getTargetBundle(),
                'flow_task_mode' => $flow->getTaskMode(),
                'flow_qualifier_index' => $i,
              ]),
              'weight' => 10,
            ];
          }
          if ($flow->access('delete')) {
            $in_use = FALSE;
            foreach ($task_subjects as $task_subject) {
              if (FlowCompatibility::validate($flow, $task_subject, $qualifier)) {
                $in_use = TRUE;
                break;
              }
            }
            if (!$in_use) {
              $operations['delete'] = [
                'title' => $this->t('Delete'),
                'url' => Url::fromRoute("flow.qualifier.{$target_type->id()}.delete", [
                  'entity_type_id' => $target_type->id(),
                  $bundle_type_id => $flow->getTargetBundle(),
                  'flow_task_mode' => $flow->getTaskMode(),
                  'flow_qualifier_index' => $i,
                ]),
                'weight' => 20,
              ];
            }
          }
          $form['custom']['qualifiers'][$i]['operations'] = [
            '#type' => 'operations',
            '#links' => $operations,
            '#weight' => 30,
          ];
        }
      }
      else {
        $form['custom']['qualifiers']['empty'] = [
          '#type' => 'markup',
          '#markup' => $this->t('No qualifiers have been added yet.'),
          '#weight' => 10,
        ];
      }

      $weight += 100;
      $wrapper_id = Html::getUniqueId('flow-add-qualifier');
      $form['add_qualifier'] = [
        '#type' => $qualifiers->count() ? 'details' : 'fieldset',
        '#title' => $this->t('Add new qualifier'),
        '#prefix' => '<div id="' . $wrapper_id . '">',
        '#suffix' => '</div>',
        '#weight' => $weight++,
      ];
      $form['add_qualifier']['table'] = [
        '#type' => 'table',
        '#weight' => 10,
        '#header' => [$this->t('Qualifier'), $this->t('Subject'), ''],
        '#attributes' => [
          'class' => ['flow-form-add-qualifier'],
        ],
      ];
      // As the select lists may be large, use the select2 widget when available.
      $select_widget = $this->moduleHandler->moduleExists('select2') ? 'select2' : 'select';
      $form['add_qualifier']['table'][0] = [
        '#parents' => ['add_qualifier'],
      ];
      $form['add_qualifier']['table'][0]['qualifier'] = [
        '#type' => $select_widget,
        '#options' => $this->getQualifierOptions($form, $form_state),
        '#default_value' => '_none',
        '#empty_value' => '_none',
        '#weight' => 10,
        '#ajax' => [
          'callback' => [static::class, 'addQualifierAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
      $form['add_qualifier']['table'][0]['subject'] = [
        '#type' => $select_widget,
        '#options' => $this->getQualifyingSubjectOptions($form, $form_state),
        '#default_value' => '_none',
        '#empty_value' => '_none',
        '#weight' => 20,
        '#ajax' => [
          'callback' => [static::class, 'addQualifierAjax'],
          'wrapper' => $wrapper_id,
        ],
      ];
      if ($form_state->getValue(['add_qualifier', 'qualifier'], '_none') !== '_none'
        && $form_state->getValue(['add_qualifier', 'subject'], '_none') !== '_none') {
        $form['add_qualifier']['table'][0]['link'] = [
          '#type' => 'link',
          '#attributes' => [
            'class' => ['button', 'button-action', 'button--primary'],
          ],
          '#title' => $this->t('Add qualifier'),
          '#url' => Url::fromRoute("flow.qualifier.{$target_type->id()}.add", [
            'entity_type_id' => $target_type->id(),
            $bundle_type_id => $flow->getTargetBundle(),
            'flow_task_mode' => $flow->getTaskMode(),
            'flow_qualifier_plugin' => $form_state->getValue(['add_qualifier', 'qualifier']),
            'flow_subject_plugin' => $form_state->getValue(['add_qualifier', 'subject']),
          ]),
        ];
      }
      else {
        $form['add_qualifier']['table'][0]['link'] = [
          '#type' => 'button',
          '#attributes' => [
            'class' => ['button', 'button-action', 'button--primary'],
          ],
          '#value' => $this->t('Add qualifier'),
          '#disabled' => TRUE,
        ];
      }
    }

    $mode = $custom ? $custom['baseMode'] : $flow->getTaskMode();
    $execution_options = [
      'now' => $this->t('On @mode', ['@mode' => $mode]),
      'after' => $this->t('After @mode', ['@mode' => $mode]),
      'queue' => $this->t('Background queue'),
    ];

    $form['active'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Active tasks'),
      '#weight' => $weight++,
    ];
    $grouped_tasks = [
      'active' => $flow->getTasks(Flow::$filter),
      'inactive' => $flow->getTasks(['active' => FALSE]),
    ];
    $grouped_subjects = [
      'active' => $flow->getSubjects(Flow::$filter),
      'inactive' => $flow->getSubjects(['active' => FALSE]),
    ];
    foreach ($grouped_tasks as $group => $tasks) {
      $subjects = $grouped_subjects[$group];
      if ($tasks->count()) {
        if ($group === 'inactive') {
          $form['inactive'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Tasks not active'),
            '#weight' => $weight++,
          ];
        }
        $form[$group]['tasks'] = [
          '#attributes' => ['id' => Html::getUniqueId('flow-' . $group . '-tasks')],
          '#type' => 'table',
          '#parents' => ['tasks'],
          '#header' =>
            [
              $this->t('Weight'),
              $this->t('Task'),
              $this->t('Subject'),
              $this->t('Execution'),
              $this->t('Operations'),
            ],
          '#weight' => 10,
          '#tabledrag' => [
            [
              'action' => 'order',
              'relationship' => 'sibling',
              'group' => 'flow-task-weight',
            ],
          ],
        ];
        /** @var \Drupal\flow\Plugin\FlowTaskBase $task */
        foreach ($tasks as $i => $task) {
          $subject = $subjects->get($i);
          $task_configuration = $task->getConfiguration();
          $form[$group]['tasks'][$i] = [
            '#attributes' => ['class' => ['draggable']],
            '#weight' => $i,
          ];
          $form[$group]['tasks'][$i]['weight'] = [
            '#type' => 'weight',
            '#title' => $this->t('Weight'),
            '#title_display' => 'invisible',
            '#default_value' => $task_configuration['weight'],
            '#attributes' => ['class' => ['flow-task-weight']],
            '#delta' => 50,
            '#weight' => 10,
          ];
          $form[$group]['tasks'][$i]['task'] = [
            '#type' => 'markup',
            '#markup' => $task->getPluginDefinition()['label'],
            '#weight' => 20,
          ];
          $form[$group]['tasks'][$i]['subject'] = [
            '#type' => 'markup',
            '#markup' => $subject->getPluginDefinition()['label'],
            '#weight' => 30,
          ];
          $form[$group]['tasks'][$i]['execution'] = [
            '#type' => 'markup',
            '#markup' => $execution_options[$task->configuration()['execution']['start'] ?? 'now'],
            '#weight' => 40,
          ];
          $operations = [];
          if ($flow->access('update')) {
            $operations['edit'] = [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute("flow.task.{$target_type->id()}.edit", [
                'entity_type_id' => $target_type->id(),
                $bundle_type_id => $flow->getTargetBundle(),
                'flow_task_mode' => $flow->getTaskMode(),
                'flow_task_index' => $i,
              ]),
              'weight' => 10,
            ];
            if ($group === 'active') {
              $operations['disable'] = [
                'title' => $this->t('Disable'),
                'url' => Url::fromRoute("flow.task.{$target_type->id()}.disable", [
                  'entity_type_id' => $target_type->id(),
                  $bundle_type_id => $flow->getTargetBundle(),
                  'flow_task_mode' => $flow->getTaskMode(),
                  'flow_task_index' => $i,
                ]),
                'weight' => 20,
              ];
            }
            elseif ($group === 'inactive') {
              $operations['enable'] = [
                'title' => $this->t('Enable'),
                'url' => Url::fromRoute("flow.task.{$target_type->id()}.enable", [
                  'entity_type_id' => $target_type->id(),
                  $bundle_type_id => $flow->getTargetBundle(),
                  'flow_task_mode' => $flow->getTaskMode(),
                  'flow_task_index' => $i,
                ]),
                'weight' => 20,
              ];
            }
          }
          if ($flow->access('delete') && $group === 'inactive') {
            $operations['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute("flow.task.{$target_type->id()}.delete", [
                'entity_type_id' => $target_type->id(),
                $bundle_type_id => $flow->getTargetBundle(),
                'flow_task_mode' => $flow->getTaskMode(),
                'flow_task_index' => $i,
              ]),
              'weight' => 30,
            ];
          }
          $form[$group]['tasks'][$i]['operations'] = [
            '#type' => 'operations',
            '#links' => $operations,
            '#weight' => 50,
          ];
        }
      }
      elseif ($group === 'active') {
        $form[$group]['tasks']['empty'] = [
          '#type' => 'markup',
          '#markup' => $this->t('No tasks have been added yet.'),
          '#weight' => 10,
        ];
      }
    }

    $weight += 100;
    $wrapper_id = Html::getUniqueId('flow-add-task');
    $form['add_task'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Add new task'),
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      '#weight' => $weight++,
    ];

    if ($custom && !$qualifiers->count()) {
      $form['add_task']['need_qualifier'] = [
        '#type' => 'markup',
        '#markup' => $this->t('Tasks can be added when at least one qualifier has been added above.'),
        '#weight' => 10,
      ];
      $form['actions']['#weight'] = $weight++;
      return $form;
    }

    $form['add_task']['table'] = [
      '#type' => 'table',
      '#weight' => 10,
      '#header' => [$this->t('Task'), $this->t('Subject'), ''],
      '#attributes' => [
        'class' => ['flow-form-add-task'],
      ],
    ];
    // As the select lists may be large, use the select2 widget when available.
    $select_widget = $this->moduleHandler->moduleExists('select2') ? 'select2' : 'select';
    $form['add_task']['table'][0] = [
      '#parents' => ['add_task'],
    ];
    $form['add_task']['table'][0]['task'] = [
      '#type' => $select_widget,
      '#options' => $this->getTaskOptions($form, $form_state),
      '#default_value' => '_none',
      '#empty_value' => '_none',
      '#weight' => 10,
      '#ajax' => [
        'callback' => [static::class, 'addTaskAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    $form['add_task']['table'][0]['subject'] = [
      '#type' => $select_widget,
      '#options' => $this->getSubjectOptions($form, $form_state),
      '#default_value' => '_none',
      '#empty_value' => '_none',
      '#weight' => 20,
      '#ajax' => [
        'callback' => [static::class, 'addTaskAjax'],
        'wrapper' => $wrapper_id,
      ],
    ];
    if ($form_state->getValue(['add_task', 'task'], '_none') !== '_none'
      && $form_state->getValue(['add_task', 'subject'], '_none') !== '_none') {
      $form['add_task']['table'][0]['link'] = [
        '#type' => 'link',
        '#attributes' => [
          'class' => ['button', 'button-action', 'button--primary'],
        ],
        '#title' => $this->t('Add task'),
        '#url' => Url::fromRoute("flow.task.{$target_type->id()}.add", [
          'entity_type_id' => $target_type->id(),
          $bundle_type_id => $flow->getTargetBundle(),
          'flow_task_mode' => $flow->getTaskMode(),
          'flow_task_plugin' => $form_state->getValue(['add_task', 'task']),
          'flow_subject_plugin' => $form_state->getValue(['add_task', 'subject']),
        ]),
      ];
    }
    else {
      $form['add_task']['table'][0]['link'] = [
        '#type' => 'button',
        '#attributes' => [
          'class' => ['button', 'button-action', 'button--primary'],
        ],
        '#value' => $this->t('Add task'),
        '#disabled' => TRUE,
      ];
    }

    $form['actions']['#weight'] = $weight++;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$this->entity->access('update')) {
      return;
    }
    // Remove button and internal Form API values from submitted values.
    $form_state->cleanValues();
    /** @var \Drupal\flow\Entity\FlowInterface $flow */
    $flow = $this->entity;
    // The only thing that can be done within this form is changing the weight
    // order of the configured task plugins. Update the new weights accordingly.
    $tasks_array = $flow->get('tasks');
    foreach ($form_state->getValue('tasks', []) as $i => $task_values) {
      $tasks_array[$i]['weight'] = $task_values['weight'];
    }
    $flow->setTasks($tasks_array);
  }

  /**
   * {@inheritdoc}
   */
  public function afterBuild(array $element, FormStateInterface $form_state) {
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\flow\Entity\FlowInterface $config */
    $config = $this->entity;

    $config->save();

    $task_modes = FlowTaskMode::service()->getAvailableTaskModes();

    $t_args = [
      '%label' => $config->isCustom() ? $config->get('custom')['label'] : $task_modes[$config->getTaskMode()],
      '%type' => \Drupal::entityTypeManager()->getDefinition($config->getTargetEntityTypeId())->getLabel(),
    ];
    $message = $config->isCustom() ? $this->t('The custom %label flow configuration for %type has been saved.', $t_args)
      : $this->t('The %label flow configuration for %type has been saved.', $t_args);

    $this->messenger()->addStatus($message);
  }

  /**
   * Get available subject options.
   *
   * @param array $form
   *   The current form build array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The according form state.
   *
   * @return array
   *   The subject options.
   */
  protected function getSubjectOptions(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\flow\Entity\FlowInterface $config */
    $config = $this->entity;
    $flow_keys = [
      'entity_type_id' => $config->getTargetEntityTypeId(),
      'bundle' => $config->getTargetBundle(),
      'task_mode' => $config->isCustom() ? $config->get('custom')['baseMode'] : $config->getTaskMode(),
    ];

    $options = ['_none' => $this->t('- Select a subject -')];
    $task_plugin_id = $form_state->getValue(['add_task', 'task'], '_none');
    $task_plugins = [];
    $qualifier_plugins = $config->isCustom() ? $config->getQualifiers(Flow::$filter) : [];
    if ($task_plugin_id !== '_none') {
      $task_plugin = $this->taskManager->createInstance($task_plugin_id, $flow_keys);
      if (FlowCompatibility::validate($config, $task_plugin)) {
        $task_plugins[] = $task_plugin;
      }
    }
    else {
      foreach (array_keys($this->taskManager->getDefinitions()) as $task_plugin_id) {
        $task_plugin = $this->taskManager->createInstance($task_plugin_id, $flow_keys);
        if (FlowCompatibility::validate($config, $task_plugin)) {
          $task_plugins[] = $task_plugin;
        }
      }
    }
    foreach ($this->subjectManager->getDefinitions() as $id => $definition) {
      $plugin = $this->subjectManager->createInstance($id, $flow_keys);
      if (!FlowCompatibility::validate($config, $plugin)) {
        continue;
      }
      if ($config->isCustom()) {
        if (strpos($id, 'qualified:') === FALSE) {
          // Only qualified subjects and items from qualified subjects are
          // allowed for custom flow.
          continue;
        }
        $qualifier_exists = FALSE;
        /** @var \Drupal\flow\Plugin\FlowQualifierInterface $qualifier */
        foreach ($qualifier_plugins as $qualifier_plugin) {
          if (FlowCompatibility::validate($config, $plugin instanceof Reference ? $plugin->getSourceSubject() : $plugin, $qualifier_plugin)) {
            $qualifier_exists = TRUE;
            break;
          }
        }
        if (!$qualifier_exists) {
          continue;
        }
      }
      elseif (strpos($id, 'qualified:') !== FALSE) {
        // Qualified subjects and items from qualified subjects are only
        // available in custom flow.
        continue;
      }
      $is_compatible = FALSE;
      foreach ($task_plugins as $task_plugin) {
        if (FlowCompatibility::validate($config, $plugin, $task_plugin)) {
          $is_compatible = TRUE;
          break;
        }
      }
      if ($is_compatible) {
        $options[$id] = $definition['label'];
      }
    }
    uasort($options, static function ($a, $b) {
      return strnatcasecmp((string) $a, (string) $b);
    });
    return $options;
  }

  /**
   * Get available task options.
   *
   * @param array $form
   *   The current form build array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The according form state.
   *
   * @return array
   *   The task options.
   */
  protected function getTaskOptions(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\flow\Entity\FlowInterface $config */
    $config = $this->entity;
    $flow_keys = [
      'entity_type_id' => $config->getTargetEntityTypeId(),
      'bundle' => $config->getTargetBundle(),
      'task_mode' => $config->isCustom() ? $config->get('custom')['baseMode'] : $config->getTaskMode(),
    ];

    $options = ['_none' => $this->t('- Select a task -')];
    $subject_plugin_id = $form_state->getValue(['add_task', 'subject'], '_none');
    $subject_plugins = [];
    if ($subject_plugin_id !== '_none') {
      $subject_plugin = $this->subjectManager->createInstance($subject_plugin_id, $flow_keys);
      if (FlowCompatibility::validate($config, $subject_plugin)) {
        $subject_plugins[] = $subject_plugin;
      }
    }
    else {
      foreach (array_keys($this->subjectManager->getDefinitions()) as $subject_plugin_id) {
        $subject_plugin = $this->subjectManager->createInstance($subject_plugin_id, $flow_keys);
        if (FlowCompatibility::validate($config, $subject_plugin)) {
          $subject_plugins[] = $subject_plugin;
        }
      }
    }
    foreach ($this->taskManager->getDefinitions() as $id => $definition) {
      $plugin = $this->taskManager->createInstance($id, $flow_keys);
      if (!FlowCompatibility::validate($config, $plugin)) {
        continue;
      }
      $is_compatible = FALSE;
      foreach ($subject_plugins as $subject_plugin) {
        if (FlowCompatibility::validate($config, $plugin, $subject_plugin)) {
          $is_compatible = TRUE;
          break;
        }
      }
      if ($is_compatible) {
        $options[$id] = $definition['label'];
      }
    }
    uasort($options, static function ($a, $b) {
      return strnatcasecmp((string) $a, (string) $b);
    });
    return $options;
  }

  /**
   * Get available qualifier options.
   *
   * @param array $form
   *   The current form build array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The according form state.
   *
   * @return array
   *   The qualifier options.
   */
  protected function getQualifierOptions(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\flow\Entity\FlowInterface $config */
    $config = $this->entity;
    $flow_keys = [
      'entity_type_id' => $config->getTargetEntityTypeId(),
      'bundle' => $config->getTargetBundle(),
      'task_mode' => $config->isCustom() ? $config->get('custom')['baseMode'] : $config->getTaskMode(),
    ];

    $options = ['_none' => $this->t('- Select a qualifier -')];
    $subject_plugin_id = $form_state->getValue(['add_qualifier', 'subject'], '_none');
    $subject_plugins = [];
    if ($subject_plugin_id !== '_none') {
      $subject_plugin = $this->subjectManager->createInstance($subject_plugin_id, $flow_keys);
      if (FlowCompatibility::validate($config, $subject_plugin)) {
        $subject_plugins[] = $subject_plugin;
      }
    }
    else {
      foreach (array_keys($this->subjectManager->getDefinitions()) as $subject_plugin_id) {
        $subject_plugin = $this->subjectManager->createInstance($subject_plugin_id, $flow_keys);
        if (FlowCompatibility::validate($config, $subject_plugin)) {
          $subject_plugins[] = $subject_plugin;
        }
      }
    }
    foreach ($this->qualifierManager->getDefinitions() as $id => $definition) {
      $plugin = $this->qualifierManager->createInstance($id, $flow_keys);
      if (!FlowCompatibility::validate($config, $plugin)) {
        continue;
      }
      $is_compatible = FALSE;
      foreach ($subject_plugins as $subject_plugin) {
        if (FlowCompatibility::validate($config, $plugin, $subject_plugin)) {
          $is_compatible = TRUE;
          break;
        }
      }
      if ($is_compatible) {
        $options[$id] = $definition['label'];
      }
    }
    uasort($options, static function ($a, $b) {
      return strnatcasecmp((string) $a, (string) $b);
    });
    return $options;
  }

  /**
   * Get available qualifying subject options.
   *
   * @param array $form
   *   The current form build array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The according form state.
   *
   * @return array
   *   The qualifying subject options.
   */
  protected function getQualifyingSubjectOptions(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\flow\Entity\FlowInterface $config */
    $config = $this->entity;
    $flow_keys = [
      'entity_type_id' => $config->getTargetEntityTypeId(),
      'bundle' => $config->getTargetBundle(),
      'task_mode' => $config->isCustom() ? $config->get('custom')['baseMode'] : $config->getTaskMode(),
    ];

    $options = ['_none' => $this->t('- Select a subject -')];
    $qualifier_plugin_id = $form_state->getValue(['add_qualifier', 'qualifier'], '_none');
    $qualifier_plugins = [];
    if ($qualifier_plugin_id !== '_none') {
      $qualifier_plugin = $this->qualifierManager->createInstance($qualifier_plugin_id, $flow_keys);
      if (FlowCompatibility::validate($config, $qualifier_plugin)) {
        $qualifier_plugins[] = $qualifier_plugin;
      }
    }
    else {
      foreach (array_keys($this->qualifierManager->getDefinitions()) as $qualifier_plugin_id) {
        $qualifier_plugin = $this->qualifierManager->createInstance($qualifier_plugin_id, $flow_keys);
        if (FlowCompatibility::validate($config, $qualifier_plugin)) {
          $qualifier_plugins[] = $qualifier_plugin;
        }
      }
    }
    foreach ($this->subjectManager->getDefinitions() as $id => $definition) {
      $plugin = $this->subjectManager->createInstance($id, $flow_keys);
      if (!FlowCompatibility::validate($config, $plugin)) {
        continue;
      }
      if (strpos($id, 'qualified:') !== FALSE) {
        // Qualifying subjects cannot be already qualified.
        continue;
      }
      $is_compatible = FALSE;
      foreach ($qualifier_plugins as $qualifier_plugin) {
        if (FlowCompatibility::validate($config, $plugin, $qualifier_plugin)) {
          $is_compatible = TRUE;
          break;
        }
      }
      if ($is_compatible) {
        $options[$id] = $definition['label'];
      }
    }
    uasort($options, static function ($a, $b) {
      return strnatcasecmp((string) $a, (string) $b);
    });
    return $options;
  }

  /**
   * Ajax callback for adding a new task.
   *
   * @param array $form
   *   The current form build array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The according form state.
   *
   * @return array
   *   The part of the form that got refreshed via Ajax.
   */
  public static function addTaskAjax(array $form, FormStateInterface $form_state): array {
    return $form['add_task'];
  }

  /**
   * Ajax callback for adding a new qualifier.
   *
   * @param array $form
   *   The current form build array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The according form state.
   *
   * @return array
   *   The part of the form that got refreshed via Ajax.
   */
  public static function addQualifierAjax(array $form, FormStateInterface $form_state): array {
    return $form['add_qualifier'];
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state) {
    $actions = [];

    $actions['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm', '::save'],
      '#weight' => 10,
    ];

    /** @var \Drupal\flow\Entity\FlowInterface $flow */
    $flow = $this->entity;
    $target_type = $this->entityTypeManager->getDefinition($flow->getTargetEntityTypeId());
    $bundle_type_id = $target_type->getBundleEntityType() ?: 'bundle';

    if (!$flow->isNew() && !$flow->getTasks()->count()) {
      $actions['delete'] = [
        '#type' => 'link',
        '#title' => $this->t('Delete'),
        '#access' => $this->entity->access('delete'),
        '#attributes' => [
          'class' => ['button', 'button--danger'],
        ],
        '#url' => Url::fromRoute("entity.flow.{$target_type->id()}.delete", [
          'entity_type_id' => $target_type->id(),
          $bundle_type_id => $flow->getTargetBundle(),
          'flow_task_mode' => $flow->getTaskMode(),
        ]),
        '#weight' => 20,
      ];
    }

    return $actions;
  }

}
