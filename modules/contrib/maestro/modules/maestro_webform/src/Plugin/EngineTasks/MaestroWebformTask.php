<?php

namespace Drupal\maestro_webform\Plugin\EngineTasks;

use Drupal\node\Entity\NodeType;
use Drupal\webform\Controller\WebformSubmissionViewController;
use Drupal\Core\Url;
use Drupal\webform\Entity\WebformSubmission;
use Drupal\Core\Plugin\PluginBase;
use Drupal\maestro\MaestroEngineTaskInterface;
use Drupal\maestro\MaestroTaskTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;
use Drupal\maestro\Form\MaestroExecuteInteractive;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\webform\Entity\Webform;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Routing\TrustedRedirectResponse;

/**
 * Maestro Webform Task Plugin.
 *
 * @Plugin(
 *   id = "MaestroWebform",
 *   task_description = @Translation("The Maestro Engine's Interactive Webform task."),
 * )
 */
class MaestroWebformTask extends PluginBase implements MaestroEngineTaskInterface {

  // Please see the \Drupal\maestro\MaestroTaskTrait for details on what's included.
  use MaestroTaskTrait;

  /**
   * Constructor.
   *
   * @param array $configuration
   *   The incoming configuration information from the engine execution.
   *   [0] - is the process ID
   *   [1] - is the queue ID
   *   The processID and queueID properties are defined in the MaestroTaskTrait.
   */
  public function __construct(array $configuration = NULL) {
    if (is_array($configuration)) {
      $this->processID = $configuration[0];
      $this->queueID = $configuration[1];
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isInteractive() {
    /*
     * Webform Task type is interactive allowing the end user to interact with the Maestro Task Console..
     */
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function shortDescription() {
    return $this->t('Webfom Task');
  }

  /**
   * {@inheritDoc}
   */
  public function description() {
    return $this->t('Maestro Interactive Webform Task.');
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\Component\Plugin\PluginBase::getPluginId()
   */
  public function getPluginId() {
    // The ID of the plugin.  Should match the @id shown in the annotation.
    return 'MaestroWebform';
  }

  /**
   * {@inheritDoc}
   */
  public function getTaskColours() {
    // Using the Blue task box as we've historically used blue for interactive.
    return '#0000ff';
  }

  /**
   * Part of the ExecutableInterface
   * Execution of the Example task returns TRUE and does nothing else.
   * {@inheritdoc}.
   */
  public function execute() {
    /*
     * Setting our run_once flag so that the engine doesn't have to keep trying to process this task.
     */

    $queueRecord = \Drupal::entityTypeManager()->getStorage('maestro_queue')->load($this->queueID);
    $queueRecord->set('run_once', 1);
    $queueRecord->save();
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function getExecutableForm($modal, MaestroExecuteInteractive $parent) {
    /*
     * This will be our base form for displaying the webform submission to the end user through the task console.
     */
    $webform_config = \Drupal::config('webform.settings');
    $form['#title'] = $this->t('Submission Review');

    // Load the task's configuration so that we can determine which webform and unique identifier this
    // particular task will be using.
    $templateTask = MaestroEngine::getTemplateTaskByQueueID($this->queueID);
    $taskUniqueSubmissionId = $templateTask['data']['unique_id'];
    $webformMachineName = $templateTask['data']['webform_machine_name'];

    $queueEntry = MaestroEngine::getQueueEntryById($this->queueID);
    if ($queueEntry) {
      $started_date = intval($queueEntry->started_date->getString());
      $created_date = intval($queueEntry->created->getString());
      // We will set the started date to the FIRST time someone clicks on the execute of the task.
      // when we create a task, we set the started_date to the time the entity is created.
      if ($started_date - $created_date < 5) {
        // There could be some slack between the started date and the created date just due to latency in task and entity creation.
        // giving it 5s should be enough time.
        $queueEntry->set('started_date', time());
        $queueEntry->save();
      }
    }

    // Determine if the Webform Task has a node it is attached to set in the task's definition.
    // Signals nothing selected.
    $webformNode = FALSE;
    if (isset($templateTask['data']['use_nodes_attached']) && $templateTask['data']['use_nodes_attached'] == 1) {
      // Task has been flagged as requiring the webform node to be utilized.
      if (isset($templateTask['data']['webform_nodes_attached_variable']) && $templateTask['data']['webform_nodes_attached_variable'] != 'none') {
        // Task is using the process variable to get the node ID. Intval it to ensure it's an integer.
        $node_id = intval(MaestroEngine::getProcessVariable($templateTask['data']['webform_nodes_attached_variable'], $this->processID));
        $webformNode = 'node/' . $node_id;
      }
      elseif (isset($templateTask['data']['webform_nodes_attached_to']) && $templateTask['data']['webform_nodes_attached_to'] != 'none') {
        // Task is using the selectbox value for which node to show.
        $webformNode = $templateTask['data']['webform_nodes_attached_to'];
      }
      // If we get here without the if/elseif firing, webformNode is FALSE.
    }

    // Determine if the webform's $taskUniqueSubmissionId exists in the "webforms" process variable.
    // If it exists, show it to the user.
    // If not, then bring it up to be created.
    // Load a webform submission by loading this task's unique identifier.
    $sid = MaestroEngine::getEntityIdentiferByUniqueID($this->processID, $taskUniqueSubmissionId);
    $webform_submission = NULL;
    if ($sid) {
      $webform_submission = WebformSubmission::load($sid);
    }
    if ($webform_submission) {
      // We have a submission.  Let's now see if we should be showing the edit page.
      if (isset($templateTask['data']['show_edit_form']) && $templateTask['data']['show_edit_form'] == 1) {
        //The inclusion of "structure" in the URL for editing the webform may not exist if Webform is configured to show
        //the webform as a main menu item rather than under the structure path.
        $ui_toolbar = $webform_config->get('ui.toolbar_item');
        if($ui_toolbar) {
          $url = Url::fromUserInput('/admin/webform/manage/' . $webformMachineName . '/submission/' . $sid . '/edit', ['query' => ['maestro' => 1, 'queueid' => $this->queueID]]);
        }
        else {
          $url = Url::fromUserInput('/admin/structure/webform/manage/' . $webformMachineName . '/submission/' . $sid . '/edit', ['query' => ['maestro' => 1, 'queueid' => $this->queueID]]);
        }

        $response = new RedirectResponse($url->toString());
        return $response;
      }
      else {
        $container = \Drupal::getContainer();
        $webformSubmissionViewController = WebformSubmissionViewController::create($container);
        $webform_build_view = $webformSubmissionViewController->view($webform_submission, 'table', $langcode = NULL);

        $form['submission_information'] = $webform_build_view['information'];
        $form['submission_information']['#open'] = FALSE;
        // We just want the submission.
        $form['submission_data'] = $webform_build_view['submission'];
      }

    }
    // No submission entity exists, but we DO we have a submission ID?
    else {
      // Not able to retrieve the webform submission.
      if ($sid !== FALSE && $sid !== NULL) {
        \Drupal::messenger()->addError(t('The submission attached to this workflow was unable to be fetched.'));
        $form['status_messages'] = [
          '#type' => 'status_messages',
          '#weight' => -15,
        ];
      }
      // No submission, so redirect to the webform creation.
      else {
        if ($webformNode && $webformNode !== FALSE) {
          $url = Url::fromUserInput('/' . $webformNode, ['query' => ['maestro' => 1, 'queueid' => $this->queueID]]);
        }
        else {
          $url = Url::fromUserInput('/webform/' . $webformMachineName, ['query' => ['maestro' => 1, 'queueid' => $this->queueID]]);
        }

        $response = new RedirectResponse($url->toString());
        return $response;
      }
    }

    $form['queueID'] = [
      '#type' => 'hidden',
      '#title' => $this->t('The queue ID of this task'),
      '#default_value' => $this->queueID,
      '#description' => $this->t('queueID'),
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Accept'),
    ];

    $form['actions']['reject'] = [
      '#type' => 'submit',
      '#value' => $this->t('Reject'),
    ];

    $form['#attached']['library'][] = 'maestro_webform/maestro-webform-css';

    // Does the developer/admin wish to attach any custom form elements?  Let them do so here:
    \Drupal::moduleHandler()->invokeAll('maestro_webform_submission_form',
        [$this->queueID, &$form, &$this]);

    return $form;

  }

  /**
   * {@inheritDoc}
   */
  public function handleExecuteSubmit(array &$form, FormStateInterface $form_state) {
    $completeTask = TRUE;
    $queueID = intval($form_state->getValue('maestro_queue_id'));
    $triggeringElement = $form_state->getTriggeringElement();
    $templateTask = MaestroEngine::getTemplateTaskByQueueID($queueID);
    if (strstr($triggeringElement['#id'], 'edit-submit') !== FALSE && $queueID > 0) {
      MaestroEngine::completeTask($queueID, \Drupal::currentUser()->id());
    }
    else {
      // Complete the task, but we'll also flag it as TASK_STATUS_CANCEL
      // Let the devs manage the submission as well:
      \Drupal::moduleHandler()->invokeAll('maestro_webform_submission_set_cancel_completion_status',
          [$queueID, &$form, &$form_state, $triggeringElement, &$completeTask]);
      if ($completeTask) {
        MaestroEngine::completeTask($queueID, \Drupal::currentUser()->id());
        MaestroEngine::setTaskStatus($queueID, TASK_STATUS_CANCEL);
      }
    }

    // Redirect based on where the task told us to go.
    if (isset($templateTask['data']['redirect_to']) && $templateTask['data']['redirect_to'] != '') {
      $response = new TrustedRedirectResponse('/' . $templateTask['data']['redirect_to']);
      $form_state->setResponse($response);
    }
    else {
      $response = new TrustedRedirectResponse('/taskconsole');
      $form_state->setResponse($response);
    }

    // Let the devs manage the submission as well:
    \Drupal::moduleHandler()->invokeAll('maestro_webform_submission_form_submit',
        [$queueID, &$form, &$form_state, $triggeringElement]);

  }

  /**
   * {@inheritDoc}
   */
  public function getTaskEditForm(array $task, $templateMachineName) {
    // let's get all the webforms established in the system.
    $webforms = Webform::loadMultiple();
    // $webforms is an array where the keys are the machine names of the webform and values are the webform entity.
    // the $webform[$key]->title is the human readable name.
    $webform_options = ['' => $this->t('Please Choose')];
    foreach ($webforms as $key => $webform) {
      $webform_options[$key] = $webform->get('title');
    }

    $form['webform_machine_name'] = [
      '#type' => 'select',
      '#options' => $webform_options,
      '#title' => $this->t('Webform'),
      '#description' => $this->t('The Webform you wish to use when no previous submissions tagged with the Unique Identifier (next field) exist in the workflow.
          If a submission exists in the workflow, this field is used to show the webform\'s output.'),
      '#default_value' => isset($task['data']['webform_machine_name']) ? $task['data']['webform_machine_name'] : '',
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'fetchNodesAttached'],
        'event' => 'change',
        'wrapper' => 'attached-nodes',
        'progress' => [
          'type' => 'throbber',
          'message' => t('Fetching Nodes Attached...'),
        ],
      ],
    ];

    $form['unique_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Unique Identifier'),
      '#description' => $this->t('The name of the key used when tracking the webform content for the maestro process. By default the identifier is "submission".'),
      '#default_value' => isset($task['data']['unique_id']) ? $task['data']['unique_id'] : '',
      '#required' => TRUE,
    ];

    $form['show_edit_form'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show the edit form if submission already exists?'),
      '#description' => $this->t('If a webform submission already exists, checking this option will send you to the edit form for the submission rather than the summary.'),
      '#default_value' => isset($task['data']['show_edit_form']) ? $task['data']['show_edit_form'] : '0',
    ];

    $form['use_nodes_attached'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Webform attached to a node?'),
      '#description' => $this->t('Is your webform attached to a node?
          When checked, signals Maestro that your entry/edit of a webform depends on the node it is attached to.'),
      '#default_value' => isset($task['data']['use_nodes_attached']) ? $task['data']['use_nodes_attached'] : '0',
    ];

    $form['nodes_attached'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Webform Attached to Node Settings'),
      '#states' => [
        'visible' => [
          ':input[name="use_nodes_attached"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $variables = MaestroEngine::getTemplateVariables($templateMachineName);
    $options = [];
    $options['none'] = $this->t('Do not use process variable');
    foreach ($variables as $variableName => $arr) {
      $options[$variableName] = $variableName;
    }
    $form['nodes_attached']['webform_nodes_attached_variable'] = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Specify a variable to use that holds a Node ID.'),
      '#description' => $this->t('The variable selected must be populated by a single Node ID which the chosen webform is attached to.'),
      '#default_value' => isset($task['data']['webform_nodes_attached_variable']) ? $task['data']['webform_nodes_attached_variable'] : '',
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="use_nodes_attached"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Based on the webform_machine_name, we do an auto-lookup to determine if any nodes are bound to this webform and
    // allow the administrator to choose a node to use to show the webform to the end user for editing/creating.
    // this alters the webform's URI when presenting add/edit pages.
    $options = $this->_getAttachedNodeOptions(isset($task['data']['webform_machine_name']) ? $task['data']['webform_machine_name'] : '');
    $form['nodes_attached']['webform_nodes_attached_to'] = [
      '#type' => 'select',
      '#title' => $this->t('Select a Node Attached To Specified Webform'),
      '#description' => $this->t('Enabled when you choose NOT to use a process variable. Listed are the nodes that use the specified webform.'),
      '#states' => [
        'enabled' => [
          ':input[name="webform_nodes_attached_variable"]' => ['value' => 'none'],
        ],
      ],
      '#options' => $options,
      '#default_value' => isset($task['data']['webform_nodes_attached_to']) ? $task['data']['webform_nodes_attached_to'] : 'none',
      '#required' => FALSE,
      '#prefix' => '<div id="attached-nodes">',
      '#suffix' => '</div>',
    ];

    $form['skip_webform_handlers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Skip Any Maestro Webform Submission Handlers'),
      '#description' => $this->t('Maestro allows you to spawn a workflow via a Webform submission handler. Check to bypass any Maestro webform submission handlers attached to the webform chosen.'),
      '#default_value' => isset($task['data']['skip_webform_handlers']) ? $task['data']['skip_webform_handlers'] : '0',
    ];

    $form['redirect_to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Return Path'),
      '#description' => $this->t('You can specify where your return path should go upon task completion.'),
      '#default_value' => isset($task['data']['redirect_to']) ? $task['data']['redirect_to'] : 'taskconsole',
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * Ajax callback to validate the Webform selection.  Generate a list of nodes it's attached to, if any.
   */
  public function fetchNodesAttached(array &$form, FormStateInterface $form_state) {

    $options = $this->_getAttachedNodeOptions($form_state->getValue('webform_machine_name'));
    $elem = [
      '#type' => 'select',
      '#options' => $options,
      '#title' => $this->t('Nodes Attached To selected webform'),
      '#description' => $this->t('Enabled when you choose NOT to use a process variable. Listed are the nodes that use the specified webform.'),
      '#description_display' => 'after',
      '#states' => [
        'enabled' => [
          ':input[name="webform_nodes_attached_variable"]' => ['value' => 'none'],
        ],
      ],
      '#required' => FALSE,
      '#prefix' => '<div id="attached-nodes">',
      '#suffix' => '</div>',
    ];

    $renderer = \Drupal::service('renderer');
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#attached-nodes', $renderer->render($elem)));
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function validateTaskEditForm(array &$form, FormStateInterface $form_state) {
    /*
     * Need to validate anything on your edit form?  Do that here.
     */
  }

  /**
   * {@inheritDoc}
   */
  public function prepareTaskForSave(array &$form, FormStateInterface $form_state, array &$task) {
    $task['data']['unique_id'] = $form_state->getValue('unique_id');
    $task['data']['webform_machine_name'] = $form_state->getValue('webform_machine_name');
    // Forcing this task to not be modal.
    $task['data']['modal'] = 'notmodal';
    $task['data']['skip_webform_handlers'] = $form_state->getValue('skip_webform_handlers');
    $task['data']['webform_nodes_attached_to'] = $form_state->getValue('webform_nodes_attached_to');
    $task['data']['use_nodes_attached'] = $form_state->getValue('use_nodes_attached');
    $task['data']['webform_nodes_attached_variable'] = $form_state->getValue('webform_nodes_attached_variable');
    $task['data']['redirect_to'] = $form_state->getValue('redirect_to');
    $task['data']['show_edit_form'] = $form_state->getValue('show_edit_form');

  }

  /**
   * {@inheritDoc}
   */
  public function performValidityCheck(array &$validation_failure_tasks, array &$validation_information_tasks, array $task) {
    // We do a validity check on the template in maestro_webform.module to ensure that the
    // template has a webforms process variable when a webform task is present.
  }

  /**
   * {@inheritDoc}
   */
  public function getTemplateBuilderCapabilities() {
    /*
     * We're using the stock edit, draw lines, remove lines and removal task capabilities in the editor.
     */
    return ['edit', 'drawlineto', 'removelines', 'remove'];
  }

  /**
   * Internal used method to get attached node options.
   */
  protected function _getAttachedNodeOptions($webform_machine_name) {
    $options = [];
    if ($webform_machine_name != '') {
      $field_configs = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['entity_type' => 'node']);
      $node_types = NodeType::loadMultiple();
      $ntypes = [];
      $fnames = [];

      foreach ($field_configs as $field_config) {
        if ($field_config->get('field_type') === 'webform') {
          $bundle = $field_config->get('bundle');
          $ntypes[$bundle] = $node_types[$bundle];

          $field_name = $field_config->get('field_name');
          $fnames[$field_name] = $field_name;
        }
      }
      if (count($fnames) > 0) {
        $query = \Drupal::entityTypeManager()->getStorage('node')->getQuery();
        $or = $query->orConditionGroup();
        foreach ($fnames as $field_name) {
          $or->condition($field_name . '.target_id', $webform_machine_name);
        }
        $query->condition($or);
        $query->accessCheck(FALSE);
        $result = $query->execute();
        // The result are now node IDs we can use to add to the options.
        $entities = \Drupal::entityTypeManager()->getStorage('node')->loadMultiple($result);

        foreach ($entities as $id => $node) {
          $options['node/' . $id] = $node->label();
        }
      }
    }

    if (count($options)) {
      $options = array_merge(['none' => $this->t('Not Selected')], $options);
    }
    else {
      $options['none'] = $this->t('No nodes attach this webform');
    }
    return $options;
  }

}
