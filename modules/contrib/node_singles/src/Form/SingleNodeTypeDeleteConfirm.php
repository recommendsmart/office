<?php

namespace Drupal\node_singles\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An alternative version of the standard entity delete form.
 *
 * This version offers to automatically delete the single node
 * before deleting the node type.
 */
class SingleNodeTypeDeleteConfirm extends EntityDeleteForm {

  /**
   * The settings service.
   *
   * @var \Drupal\node_singles\Service\NodeSinglesSettingsInterface
   */
  protected $settings;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->settings = $container->get('node_singles.settings');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    if ($this->entity->getThirdPartySetting('node_singles', 'is_single', FALSE)) {
      $caption = '<p>' . $this->t('Deleting this node type will also delete the associated @singleLabel and its data. Are you sure?', [
        '@singleLabel' => $this->settings->getSingularLabel(),
      ]) . '</p>';
    }
    else {
      $numNodes = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $this->entity->id())
        ->count()
        ->execute();

      if ($numNodes) {
        $caption = '<p>' . $this->formatPlural($numNodes, '%type is used by 1 piece of content on your site. You can not remove this content type until you have removed all of the %type content.', '%type is used by @count pieces of content on your site. You may not remove %type until you have removed all of the %type content.', ['%type' => $this->entity->label()]) . '</p>';
      }
    }

    $form['#title'] = $this->getQuestion();
    if (isset($caption)) {
      $form['description'] = ['#markup' => $caption];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->condition('type', $this->entity->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!empty($ids)) {
      $nodes = $storage->loadMultiple($ids);

      // Delete existing nodes.
      $storage->delete($nodes);
      $this->messenger()->addMessage($this->formatPlural(count($nodes), 'Entity is successfully deleted.', 'All @count entities are successfully deleted.'));
    }

    // Delete the node type.
    parent::submitForm($form, $form_state);
  }

}
