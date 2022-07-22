<?php

namespace Drupal\eca\Event;

use Drupal\Component\EventDispatcher\Event;
use Drupal\eca\Entity\Objects\EcaAction;
use Drupal\eca\Entity\Objects\EcaObject;

/**
 * Dispatches before a single action is being executed.
 */
class BeforeActionExecutionEvent extends Event {

  /**
   * The ECA configuration.
   *
   * @var \Drupal\eca\Entity\Objects\EcaAction
   */
  protected EcaAction $ecaAction;

  /**
   * The object that the action operates on.
   *
   * @var mixed
   */
  protected $object;

  /**
   * The triggering system event.
   *
   * @var \Drupal\Component\EventDispatcher\Event|\Symfony\Contracts\EventDispatcher\Event
   */
  protected object $event;

  /**
   * The predecessor.
   *
   * @var \Drupal\eca\Entity\Objects\EcaObject
   */
  protected EcaObject $predecessor;

  /**
   * Array holding arbitrary variables the represent a pre-execution state.
   *
   * Can be used to hold and restore values after execution.
   *
   * @var array
   */
  protected $prestate = [];

  /**
   * The BeforeActionExecutionEvent constructor.
   *
   * @param \Drupal\eca\Entity\Objects\EcaAction $ecaAction
   *   The action object as part of an ECA configuration.
   * @param mixed &$object
   *   The object that the action operates on.
   * @param \Drupal\Component\EventDispatcher\Event|\Symfony\Contracts\EventDispatcher\Event $event
   *   The triggering system event.
   * @param \Drupal\eca\Entity\Objects\EcaObject $predecessor
   *   The predecessor.
   */
  public function __construct(EcaAction $ecaAction, &$object, object $event, EcaObject $predecessor) {
    $this->ecaAction = $ecaAction;
    $this->object = $object;
    $this->event = $event;
    $this->predecessor = $predecessor;
  }

  /**
   * Get the ECA action object.
   *
   * @return \Drupal\eca\Entity\Objects\EcaAction
   *   The ECA action object.
   */
  public function getEcaAction(): EcaAction {
    return $this->ecaAction;
  }

  /**
   * Get the object that the action operates on.
   *
   * @return mixed
   *   The object.
   */
  public function &getObject() {
    return $this->object;
  }

  /**
   * Get the applying system event.
   *
   * @return \Drupal\Component\EventDispatcher\Event|\Symfony\Contracts\EventDispatcher\Event
   *   The applying system event.
   */
  public function getEvent(): object {
    return $this->event;
  }

  /**
   * Get the predecessor.
   *
   * @return \Drupal\eca\Entity\Objects\EcaObject
   *   The predecessor.
   */
  public function getPredecessor(): EcaObject {
    return $this->predecessor;
  }

  /**
   * Get the value of a prestate variable.
   *
   * @param string|null $name
   *   The name of the variable. Set to NULL to return the whole array.
   *
   * @return mixed
   *   The value. Returns NULL if not present.
   */
  public function &getPrestate(?string $name) {
    if (!isset($name)) {
      return $this->prestate;
    }

    $value = NULL;
    if (isset($this->prestate[$name])) {
      $value = &$this->prestate[$name];
    }
    return $value;
  }

  /**
   * Set the value of a prestate variable.
   *
   * @param string $name
   *   The name of the variable.
   * @param mixed &$value
   *   The value to set, passed by reference.
   */
  public function setPrestate(string $name, &$value) {
    $this->prestate[$name] = &$value;
  }

}
