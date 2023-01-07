<?php

namespace Drupal\views_default_user_taxonomy\Plugin\views\argument_default;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;
use Drupal\taxonomy\Plugin\views\argument_default\Tid as TaxonomyDefaultArgument;

/**
 * Taxonomy tid default argument with user page support.
 *
 * @ViewsArgumentDefault(
 *   id = "taxonomy_tid",
 *   title = @Translation("Taxonomy term ID from URL")
 * )
 */
class Tid extends TaxonomyDefaultArgument {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['user'] = ['default' => FALSE];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    $form['term_page'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Load default filter from term page"),
      '#default_value' => $this->options['term_page'],
    ];

    $form['node'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Load default filter from node page, that's good for related taxonomy blocks"),
      '#default_value' => $this->options['node'],
    ];

    $form['user'] = [
      '#type' => 'checkbox',
      '#title' => $this->t("Load default filter from user page, that's good for related taxonomy blocks"),
      '#default_value' => $this->options['user'],
    ];

    $form['limit'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Limit terms by vocabulary'),
      '#default_value' => $this->options['limit'],
      '#states' => [
        'visible' => [
          [
            [':input[name="options[argument_default][taxonomy_tid][node]"]' => ['checked' => TRUE]],
            'or',
            [':input[name="options[argument_default][taxonomy_tid][user]"]' => ['checked' => TRUE]],
          ],
        ],
      ],
    ];

    $options = [];
    $vocabularies = $this->vocabularyStorage->loadMultiple();
    foreach ($vocabularies as $voc) {
      $options[$voc->id()] = $voc->label();
    }

    $form['vids'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Vocabularies'),
      '#options' => $options,
      '#default_value' => $this->options['vids'],
      '#states' => [
        'visible' => [
          ':input[name="options[argument_default][taxonomy_tid][limit]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['anyall'] = [
      '#type' => 'radios',
      '#title' => $this->t('Multiple-value handling'),
      '#default_value' => $this->options['anyall'],
      '#options' => [
        ',' => $this->t('Filter to items that share all terms'),
        '+' => $this->t('Filter to items that share any term'),
      ],
      '#states' => [
        'visible' => [
          [
            [':input[name="options[argument_default][taxonomy_tid][node]"]' => ['checked' => TRUE]],
            'or',
            [':input[name="options[argument_default][taxonomy_tid][user]"]' => ['checked' => TRUE]],
          ],
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getArgument() {
    // Load default argument from taxonomy page.
    if (!empty($this->options['term_page'])) {
      if (($taxonomy_term = $this->routeMatch->getParameter('taxonomy_term')) && $taxonomy_term instanceof TermInterface) {
        return $taxonomy_term->id();
      }
    }

    // Start with a NULL entity to avoid warnings.
    $entity = NULL;

    // Load default argument from node.
    if (!empty($this->options['node'])) {
      // Just check, if a node could be detected.
      if (($node = $this->routeMatch->getParameter('node')) && $node instanceof NodeInterface) {
        $entity = $node;
      }
    }
    // Load default argument from user.
    if (!empty($this->options['user'])) {
      // Just check, if a user could be detected.
      if (($account = $this->routeMatch->getParameter('user')) && $account instanceof UserInterface) {
        $entity = $account;
      }
    }

    if (!empty($entity)) {
      $taxonomy = [];
      foreach ($entity->getFieldDefinitions() as $field) {
        if ($field->getType() == 'entity_reference' && $field->getSetting('target_type') == 'taxonomy_term') {
          foreach ($entity->get($field->getName()) as $item) {
            if (($handler_settings = $field->getSetting('handler_settings')) && isset($handler_settings['target_bundles'])) {
              $taxonomy[$item->target_id] = reset($handler_settings['target_bundles']);
            }
          }
        }
      }
      if (!empty($this->options['limit'])) {
        $tids = [];
        // Filter by vocabulary.
        foreach ($taxonomy as $tid => $vocab) {
          if (!empty($this->options['vids'][$vocab])) {
            $tids[] = $tid;
          }
        }

        return implode($this->options['anyall'], $tids);
      }
      // Return all tids.
      else {
        return implode($this->options['anyall'], array_keys($taxonomy));
      }
    }
  }

}
