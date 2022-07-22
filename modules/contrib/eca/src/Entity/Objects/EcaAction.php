<?php

namespace Drupal\eca\Entity\Objects;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\eca\Plugin\Action\ActionInterface;
use Drupal\eca\Entity\Eca;
use Drupal\Core\Action\ActionInterface as CoreActionInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\TypedData\TypedDataInterface;
use Drupal\eca\EcaEvents;
use Drupal\eca\Event\AfterActionExecutionEvent;
use Drupal\eca\Event\BeforeActionExecutionEvent;
use Drupal\eca\Plugin\ObjectWithPluginInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an ECA item of type action for internal processing.
 */
class EcaAction extends EcaObject implements ObjectWithPluginInterface {

  /**
   * Action plugin.
   *
   * @var \Drupal\Core\Action\ActionInterface
   */
  protected CoreActionInterface $plugin;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface|null
   */
  protected ?EventDispatcherInterface $eventDispatcher;

  /**
   * Event constructor.
   *
   * @param \Drupal\eca\Entity\Eca $eca
   *   The ECA config entity.
   * @param string $id
   *   The action ID provided by the modeller.
   * @param string $label
   *   The action label.
   * @param \Drupal\eca\Entity\Objects\EcaEvent $event
   *   The ECA event object which started the process towards this action.
   * @param \Drupal\Core\Action\ActionInterface $plugin
   *   The action plugin.
   */
  public function __construct(Eca $eca, string $id, string $label, EcaEvent $event, CoreActionInterface $plugin) {
    parent::__construct($eca, $id, $label, $event);
    $this->plugin = $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(EcaObject $predecessor, object $event, array $context): bool {
    if (!parent::execute($predecessor, $event, $context)) {
      return FALSE;
    }

    $access_granted = FALSE;
    $exception_thrown = FALSE;
    if ($this->plugin instanceof ActionInterface) {
      $this->plugin->setEvent($event);
    }
    elseif (($this->plugin instanceof ConfigurableInterface) && !empty($this->plugin->getConfiguration()['replace_tokens'])) {
      // When this action plugin is not related with ECA directly, that external
      // action plugin might provide configuration input where it makes sense
      // to apply Token replacement. This will only be applied when this action
      // is explicitly configured with Token replacement enabled.
      $token = $this->token();
      $fields = $this->plugin->getConfiguration();
      array_walk_recursive($fields, static function (&$value) use ($token) {
        if (is_string($value) && !empty($value)) {
          $value = $token->replaceClear($value);
        }
      });
      $this->plugin->setConfiguration($fields);
    }
    $objects = $this->getObjects($this->plugin);
    foreach ($objects as $object) {
      if ($object instanceof TypedDataInterface) {
        $value = $object->getValue();
        if ($value instanceof EntityInterface) {
          $object = $value;
        }
      }

      $before_event = new BeforeActionExecutionEvent($this, $object, $event, $predecessor);
      $this->eventDispatcher()->dispatch($before_event, EcaEvents::BEFORE_ACTION_EXECUTION);

      try {
        $access_granted = $this->plugin->access($object);
        if ($access_granted) {
          $this->plugin->execute($object);
        }
        else {
          $this->logger()->warning('Access denied to %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event.', $context);
        }
      }
      catch (\Exception $ex) {
        $context['%exmsg'] = $ex->getMessage();
        $context['%extrace'] = $ex->getTraceAsString();
        $this->logger()->error('Failed execution of %actionlabel (%actionid) from ECA %ecalabel (%ecaid) for event %event: %exmsg.\n\n%extrace', $context);
        $exception_thrown = TRUE;
      }
      finally {
        $this->eventDispatcher()->dispatch(new AfterActionExecutionEvent($this, $object, $event, $predecessor, $before_event->getPrestate(NULL), $access_granted, $exception_thrown), EcaEvents::AFTER_ACTION_EXECUTION);
      }
    }

    return $access_granted && !$exception_thrown;
  }

  /**
   * Get the plugin instance.
   *
   * @return \Drupal\Core\Action\ActionInterface
   *   The plugin instance.
   */
  public function getPlugin(): CoreActionInterface {
    return $this->plugin;
  }

  /**
   * Get the event dispatcher.
   *
   * @return \Symfony\Component\EventDispatcher\EventDispatcherInterface
   *   The event dispatcher.
   */
  protected function eventDispatcher(): EventDispatcherInterface {
    if (!isset($this->eventDispatcher)) {
      $this->eventDispatcher = \Drupal::service('event_dispatcher');
    }
    return $this->eventDispatcher;
  }

}
