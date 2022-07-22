<?php

namespace Drupal\eca_content\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca_content\Event\ContentEntityCustomEvent;
use Drupal\eca_content\Event\ContentEntityEvents;
use Drupal\eca\Service\ContentEntityTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Trigger a content entity custom event.
 *
 * @Action(
 *   id = "eca_trigger_content_entity_custom_event",
 *   label = @Translation("Trigger a custom event (entity-aware)"),
 *   type = "entity"
 * )
 */
class TriggerContentEntityCustomEvent extends ConfigurableActionBase {

  /**
   * Overrides \Drupal\eca\Plugin\ActionActionInterface::EXTERNALLY_AVAILABLE.
   *
   * @var bool
   */
  public const EXTERNALLY_AVAILABLE = TRUE;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The entity types service.
   *
   * @var \Drupal\eca\Service\ContentEntityTypes
   */
  protected ContentEntityTypes $entityTypes;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): TriggerContentEntityCustomEvent {
    $plugin = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $plugin->eventDispatcher = $container->get('event_dispatcher');
    $plugin->entityTypes = $container->get('eca.service.content_entity_types');
    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    if (!($object instanceof ContentEntityInterface)) {
      $result = AccessResult::forbidden();
      return $return_as_object ? $result : $result->isAllowed();
    }
    return parent::access($object, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    $event_id = $this->tokenServices->replaceClear($this->configuration['event_id']);
    $event = new ContentEntityCustomEvent($entity, $this->entityTypes, $event_id, ['event' => $this->event]);
    $event->addTokenNamesFromString($this->configuration['tokens']);
    $this->eventDispatcher->dispatch($event, ContentEntityEvents::CUSTOM);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'event_id' => '',
      'tokens' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['event_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Event ID'),
      '#default_value' => $this->configuration['event_id'],
      '#weight' => -20,
    ];
    $form['tokens'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Tokens to forward'),
      '#default_value' => $this->configuration['tokens'],
      '#description' => $this->t('Comma separated list of token names from the current context, that will be forwarded to the triggered event. These tokens are then also available for subsequent conditions and actions within the current process.'),
      '#weight' => -10,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['event_id'] = $form_state->getValue('event_id');
    $this->configuration['tokens'] = $form_state->getValue('tokens');
    parent::submitConfigurationForm($form, $form_state);
  }

}
