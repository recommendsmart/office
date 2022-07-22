<?php

namespace Drupal\eca\EventSubscriber;

use Drupal\eca\EcaEvents;
use Drupal\eca\Event\AfterInitialExecutionEvent;
use Drupal\eca\Event\BeforeInitialExecutionEvent;
use Drupal\eca\Event\FormEventInterface;
use Drupal\Core\Entity\EntityFormInterface;

/**
 * Adds currently involved form events into a publicly available stack.
 */
class EcaExecutionFormSubscriber extends EcaBase {

  /**
   * A stack of form events, which the subscriber involved for execution.
   *
   * @var \Drupal\eca\Event\FormEventInterface[]
   */
  protected array $eventStack = [];

  /**
   * Get the service instance of this class.
   *
   * @return \Drupal\eca\EventSubscriber\EcaExecutionFormSubscriber
   *   The service instance.
   */
  public static function get(): EcaExecutionFormSubscriber {
    return \Drupal::service('eca.execution.form_subscriber');
  }

  /**
   * Subscriber method before initial execution.
   *
   * Adds the event to the stack, and the form entity to the Token service.
   *
   * @param \Drupal\eca\Event\BeforeInitialExecutionEvent $before_event
   *   The according event.
   */
  public function onBeforeInitialExecution(BeforeInitialExecutionEvent $before_event): void {
    $event = $before_event->getEvent();
    if ($event instanceof FormEventInterface) {
      array_unshift($this->eventStack, $event);
      $form_state = $event->getFormState();
      $form_object = $form_state->getFormObject();
      if ($form_object instanceof EntityFormInterface) {
        // Only build automatically when the form state is submitted and has no
        // errors. For any other case, there is "eca_form_build_entity".
        if ($event->getForm() && $form_state->isSubmitted() && $form_state->isValidationComplete() && !$form_state->hasAnyErrors()) {
          if (empty($form_state->getValues())) {
            // @see \Drupal\eca_form\Plugin\Action\FormBuildEntity::execute()
            $form_state = clone $form_state;
            $form_state->setValues($form_state->getUserInput());
          }
          // Building the entity creates a clone of it.
          $entity = $form_object->buildEntity($event->getForm(), $form_state);
        }
        else {
          $entity = $form_object->getEntity();
        }
        $this->tokenService->addTokenData('entity', $entity);
        if ($token_type = $this->tokenService->getTokenTypeForEntityType($entity->getEntityTypeId())) {
          $this->tokenService->addTokenData($token_type, $entity);
        }
      }
    }
  }

  /**
   * Subscriber method after initial execution.
   *
   * Removes the form data provider from the Token service.
   *
   * @param \Drupal\eca\Event\AfterInitialExecutionEvent $after_event
   *   The according event.
   */
  public function onAfterInitialExecution(AfterInitialExecutionEvent $after_event): void {
    $event = $after_event->getEvent();
    if ($event instanceof FormEventInterface) {
      array_shift($this->eventStack);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[EcaEvents::BEFORE_INITIAL_EXECUTION][] = [
      'onBeforeInitialExecution',
      -100,
    ];
    $events[EcaEvents::AFTER_INITIAL_EXECUTION][] = [
      'onAfterInitialExecution',
      100,
    ];
    return $events;
  }

  /**
   * Get the stack of form events, which the subscriber involved for execution.
   *
   * @return \Drupal\eca\Event\FormEventInterface[]
   *   The stack of involved form events, which is an array ordered by the most
   *   recent events at the beginning and the first added events at the end.
   */
  public function getStackedFormEvents(): array {
    return $this->eventStack;
  }

}
