<?php

namespace Drupal\maestro\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\maestro\Engine\MaestroEngine;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Maestro Orchestrator class.
 */
class MaestroOrchestrator extends ControllerBase {

  /**
   * Orchestrator method
   * This method is called by the menu router for /orchestrator
   * This runs the Maestro Engine.
   */
  public function orchestrate($token = '', $skip_response = FALSE) {
    if ($token == '') {
      // bad!  must have a value.
      return new Response('Missing Orchestrator Token', 500);
    }

    $config = $this->config('maestro.settings');
    if ($config->get('maestro_orchestrator_token') != $token) {
      return new Response('Wrong Orchestrator Token', 500);
    }

    $engine = new MaestroEngine();
    $lockDurationTime = $config->get('maestro_orchestrator_lock_execution_time');
    if (intval($lockDurationTime) <= 0) {
      $lockDurationTime = 30;
    }

    $lock = \Drupal::lock();
    if ($lock->acquire('maestro_orchestrator', $lockDurationTime)) {
      // TODO: Handle exceptions being thrown.
      // leaving it like this will simply stall the orchestrator execution for the time being
      // How to gracefully continue?  One process failing will stall the entire engine.
      // Check if dev mode option is enabled.
      if ($config->get('maestro_orchestrator_development_mode') == '1') {
        // Does a cache reset before loading entities during orchestration.
        $engine->enableDevelopmentMode();
      }
      $engine->cleanQueue();
      $lock->release('maestro_orchestrator');
    }

    if ($engine->getDebug()) {
      return ['#markup' => 'debug orchestrator done'];
    }
    else {
      // See CronController::run as we do the same thing to return a 204 with no content to satisfy the return response.
      if (!$skip_response) {
        return new Response('', 204);
      }
    }
  }

  /**
   * Method used to start a process.
   */
  public function startProcess($templateMachineName = '', $redirect = 'taskconsole') {
    $template = MaestroEngine::getTemplate($templateMachineName);
    if ($template) {
      $engine = new MaestroEngine();
      $pid = $engine->newProcess($templateMachineName);
      if ($pid) {
        \Drupal::messenger()->addMessage(t('Process Started'));
        $config = $this->config('maestro.settings');
        // Run the orchestrator for us once on process kickoff.
        $this->orchestrate($config->get('maestro_orchestrator_token'), TRUE);
      }
      else {
        \Drupal::messenger()->addError(t('Error!  Process unable to start!'));
      }
    }
    else {
      \Drupal::messenger()->addError(t('Error!  No template by that name exits!'));
    }


    if ($redirect == 'taskconsole') {
      return new RedirectResponse(Url::fromRoute('maestro_taskconsole.taskconsole')->toString());
    }
    elseif ($redirect == 'templates') {
      return new RedirectResponse(Url::fromRoute('entity.maestro_template.list')->toString());
    }
    else {
      return new RedirectResponse(Url::fromUserInput('/' . $redirect)->toString());
    }

  }

}
