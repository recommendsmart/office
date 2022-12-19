<?php

namespace Drupal\maestro\Form;

use Drupal\user\Entity\User;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\maestro\Engine\MaestroEngine;

/**
 * Maestro Trace functionality form.
 */
class MaestroTrace extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'maestro_trace_form';
  }

  /**
   * This is the trace form.  Quite complex.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $processID = NULL) {
    // first, see if this is a legit process ID.
    $processRecord = \Drupal::entityTypeManager()
      ->getStorage('maestro_process')
      ->load($processID);
    if ($processRecord) {
      // Now we get all of the queue entries that belong to this process and display them in a simple list.
      $form = [];
      $form['#markup'] = '<div id="maestro-trace-heading">' . t('For the process') . ': ' . $processRecord->process_name->getString() . '</div>';

      $form['tasks_table'] = [
        '#type' => 'table',
        '#caption' => $this->t('Tasks in this Process'),
        '#header' => [
          [
            'data' => $this->t('Change'),
            'class' => 'maestro_hide_col',
          ],
          $this->t('Queue ID'),
          $this->t('Label'),
          $this->t('Status'),
          $this->t('Completed'),
          $this->t('By Whom'),
          $this->t('Archived'),
          $this->t('Operation'),
        ],
        '#empty' => $this->t('There are no tasks in this process!'),
        // This really shouldn't happen, but it's a catch all.
      ];

      $query = \Drupal::entityTypeManager()->getStorage('maestro_queue')->getQuery();
      $query->condition('process_id', $processID)
        ->accessCheck(FALSE);
      $entity_ids = $query->execute();
      $statusArray = MaestroEngine::getTaskStatusArray();
      $archiveArray = MaestroEngine::getTaskArchiveArray();
      foreach ($entity_ids as $queueID) {
        $queueRecord = MaestroEngine::getQueueEntryById($queueID);
        $username = 'Maestro';
        $account = User::load($queueRecord->getOwnerId());
        if ($account) {
          $username = $account->getDisplayName();
        }

        $form['tasks_table'][$queueRecord->id->getString()]['change'] = [
          '#type' => 'checkbox',
          '#wrapper_attributes' => ['class' => ['maestro_hide_col']],
          '#attributes' => [
            'title' => $this->t("Check this box to signal that this row's values should be saved."),
          ],
        ];

        $form['tasks_table'][$queueRecord->id->getString()]['queue_id'] = [
          '#plain_text' => $queueRecord->id->getString(),
        ];

        $form['tasks_table'][$queueRecord->id->getString()]['label'] = [
          '#plain_text' => $queueRecord->task_label->getString(),
        ];

        $form['tasks_table'][$queueRecord->id->getString()]['status'] = [
          '#type' => 'select',
          '#options' => $statusArray,
          '#default_value' => $queueRecord->status->getString(),
          '#attributes' => [
            'class' => ['trace-task-status'],
            'onchange' => 'turn_on_changed_flag("' . $queueRecord->id->getString() . '");',
          ],
        ];

        $completedTime = $queueRecord->completed->getString();
        if (!empty($completedTime)) {
          $form['tasks_table'][$queueRecord->id->getString()]['completed'] = [
            '#plain_text' => \Drupal::service('date.formatter')
              ->format($completedTime, 'custom', 'Y-m-d H:i:s'),
          ];
        }
        else {
          $form['tasks_table'][$queueRecord->id->getString()]['completed'] = [
            '#plain_text' => $this->t('Active'),
          ];
        }

        $form['tasks_table'][$queueRecord->id->getString()]['by_whom'] = [
          '#plain_text' => $username,
        ];

        $form['tasks_table'][$queueRecord->id->getString()]['archived'] = [
          '#type' => 'select',
          '#options' => $archiveArray,
          '#default_value' => $queueRecord->archived->getString(),
          '#attributes' => [
            'class' => ['trace-task-archived'],
            'onchange' => 'turn_on_changed_flag("' . $queueRecord->id->getString() . '");',
          ],
        ];

        $form['tasks_table'][$queueRecord->id->getString()]['delete'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Delete'),
          '#attributes' => [
            'onchange' => 'turn_on_changed_flag("' . $queueRecord->id->getString() . '");',
          ],
        ];
      } //end of foreach loop over tasks
      // Now show the template variables with an editable interface.
      $form['vars_table'] = [
        '#type' => 'table',
        '#caption' => $this->t('Variables in this Process'),
        '#header' => [
          [
            'data' => $this->t('Change'),
            'class' => 'maestro_hide_col',
          ],
          $this->t('Var ID'),
          $this->t('Var Name'),
          $this->t('Value'),
        ],
        '#empty' => $this->t('There are no variables in this process!'),
        // This really shouldn't happen, but it's a catch all.
      ];
      $query = \Drupal::entityTypeManager()->getStorage('maestro_process_variables')->getQuery();
      $query->condition('process_id', $processID)
        ->accessCheck(FALSE);
      $entity_ids = $query->execute();
      foreach ($entity_ids as $variableID) {
        $varRecord = \Drupal::entityTypeManager()
          ->getStorage('maestro_process_variables')
          ->resetCache([$variableID]);
        $varRecord = \Drupal::entityTypeManager()
          ->getStorage('maestro_process_variables')
          ->load($variableID);

        $form['vars_table'][$varRecord->id->getString()]['change'] = [
          '#type' => 'checkbox',
          '#wrapper_attributes' => ['class' => ['maestro_hide_col']],
          '#attributes' => [
            'title' => $this->t("Check this box to signal that this row's values should be saved."),
          ],
        ];
        $form['vars_table'][$varRecord->id->getString()]['id'] = [
          '#plain_text' => $varRecord->id->getString(),
        ];
        $form['vars_table'][$varRecord->id->getString()]['var_name'] = [
          '#plain_text' => $varRecord->variable_name->getString(),
        ];
        $form['vars_table'][$varRecord->id->getString()]['variable_value'] = [
          '#type' => 'textfield',
          '#default_value' => $varRecord->variable_value->getString(),
          '#attributes' => [
            'class' => ['trace-variable-value'],
            'onchange' => 'turn_on_changed_flag_vars("' . $varRecord->id->getString() . '");',
          ],
        ];

      } //end of foreach loop over variables

      //show entity identifiers in a simple format, not editable here
      $entityIdentifiers = MaestroEngine::getAllEntityIdentifiersForProcess($processID);

      $form['entity_identifiers'] = [
        '#type' => 'table',
        '#caption' => $this->t('Entity Identifiers'),
        '#header' => [
          $this->t('Unique ID'),
          $this->t('Entity Type'),
          $this->t('Entity Bundle'),
          $this->t('Entity ID'),
        ],
      ];
      foreach($entityIdentifiers as $ei) {
        $form['entity_identifiers'][] = [
          'unique_id' => [
            '#plain_text' => $ei['unique_id'],
          ],
          'entity_type' => [
            '#plain_text' => $ei['entity_type'],
          ],
          'bundle' => [
            '#plain_text' => $ei['bundle'],
          ],
          'entity_id' => [
            '#plain_text' => $ei['entity_id'],
          ],
        ];
      }

      $form['process_id'] = [
        '#type' => 'hidden',
        '#default_value' => $processID,
      ];

      $form['actions'] = [
        '#type' => 'actions',
      ];

      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
        '#name' => 'save',
      ];

      $form['actions']['delete-process'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Process'),
        '#button_type' => 'danger',
        '#name' => 'deleteprocess',
      ];

      $form['#attached'] = [
        'library' => [
          'maestro/maestro-engine-trace',
          'maestro/maestro-engine-css',
        ],
      ];

      return $form;
    }
    // This entry doesn't exist.  Stop messing around!
    else {
      \Drupal::messenger()->addError(t('Invalid process record!'));
      return ['#markup' => $this->t('Invalid Process Record. Operation Halted.')];
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // TODO:  any validation required?
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $triggering_element = $form_state->getTriggeringElement();
    if ($triggering_element and isset($triggering_element['#name']) and $triggering_element['#name'] == 'deleteprocess') {
      $tasks = $form_state->getValue('tasks_table');
      $deleteItems = [];
      foreach ($tasks as $queueID => $task) {
        $deleteItems[] = $queueID;
      }
      $form_state->setRedirect('maestro.delete.process', [
        'processID' => $form_state->getValue('process_id'),
        'idList' => implode(',', $deleteItems),
      ]);
    }
    else {
      // We are now going to skim through the list of variables and update their values if the changed flag is set.
      // This will hold all of the items that should be deleted.
      $deleteItems = [];
      $variables = $form_state->getValue('vars_table');
      foreach ($variables as $variableID => $variable) {
        if ($variable['change'] == 1) {
          // Set the process variable here.
          $varRecord = \Drupal::entityTypeManager()
            ->getStorage('maestro_process_variables')
            ->resetCache([$variableID]);
          $varRecord = \Drupal::entityTypeManager()
            ->getStorage('maestro_process_variables')
            ->load($variableID);
          $varRecord->set('variable_value', $variable['variable_value']);
          $varRecord->save();
        }
      }
      // Now do the same for the tasks.
      $tasks = $form_state->getValue('tasks_table');
      foreach ($tasks as $queueID => $task) {
        if ($task['change'] == 1) {
          $queueRecord = MaestroEngine::getQueueEntryById($queueID);
          // First check if this should be deleted.  if so, don't bother with any updates.
          if ($task['delete'] == 1) {
            // $queueRecord->delete();
            $deleteItems[] = $queueID;
          }
          else {
            // Set the queue item's values for status and archived here.
            $queueRecord->set('status', $task['status']);
            $queueRecord->set('archived', $task['archived']);
            $queueRecord->save();
          }

        }
      }

      // Now handle the deleted items via a confirm form.
      if (count($deleteItems) > 0) {
        $form_state->setRedirect('maestro.delete.task', [
          'processID' => $form_state->getValue('process_id'),
          'idList' => implode(',', $deleteItems),
        ]);

      }

    }

  }

}
