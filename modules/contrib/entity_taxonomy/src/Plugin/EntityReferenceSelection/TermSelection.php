<?php

namespace Drupal\entity_taxonomy\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_taxonomy\Entity\Vocabulary;

/**
 * Provides specific access control for the entity_taxonomy_term entity type.
 *
 * @EntityReferenceSelection(
 *   id = "default:entity_taxonomy_term",
 *   label = @Translation("entity_taxonomy Term selection"),
 *   entity_types = {"entity_taxonomy_term"},
 *   group = "default",
 *   weight = 1
 * )
 */
class TermSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'sort' => [
        'field' => 'name',
        'direction' => 'asc',
      ],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Sorting is not possible for entity_taxonomy terms because we use
    // \Drupal\entity_taxonomy\TermStorageInterface::loadTree() to retrieve matches.
    $form['sort']['#access'] = FALSE;

    return $form;

  }

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0) {
    if ($match || $limit) {
      return parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    $options = [];

    $bundles = $this->entityTypeBundleInfo->getBundleInfo('entity_taxonomy_term');
    $bundle_names = $this->getConfiguration()['target_bundles'] ?: array_keys($bundles);

    $has_admin_access = $this->currentUser->hasPermission('administer entity_taxonomy');
    $unpublished_terms = [];
    foreach ($bundle_names as $bundle) {
      if ($vocabulary = Vocabulary::load($bundle)) {
        /** @var \Drupal\entity_taxonomy\TermInterface[] $terms */
        if ($terms = $this->entityTypeManager->getStorage('entity_taxonomy_term')->loadTree($vocabulary->id(), 0, NULL, TRUE)) {
          foreach ($terms as $term) {
            if (!$has_admin_access && (!$term->isPublished() || in_array($term->parent->target_id, $unpublished_terms))) {
              $unpublished_terms[] = $term->id();
              continue;
            }
            $options[$vocabulary->id()][$term->id()] = str_repeat('-', $term->depth) . Html::escape($this->entityRepository->getTranslationFromContext($term)->label());
          }
        }
      }
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function countReferenceableEntities($match = NULL, $match_operator = 'CONTAINS') {
    if ($match) {
      return parent::countReferenceableEntities($match, $match_operator);
    }

    $total = 0;
    $referenceable_entities = $this->getReferenceableEntities($match, $match_operator, 0);
    foreach ($referenceable_entities as $bundle => $entities) {
      $total += count($entities);
    }
    return $total;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS') {
    $query = parent::buildEntityQuery($match, $match_operator);

    // Adding the 'entity_taxonomy_term_access' tag is sadly insufficient for terms:
    // core requires us to also know about the concept of 'published' and
    // 'unpublished'.
    if (!$this->currentUser->hasPermission('administer entity_taxonomy')) {
      $query->condition('status', 1);
    }
    return $query;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid) {
    $term = parent::createNewEntity($entity_type_id, $bundle, $label, $uid);

    // In order to create a referenceable term, it needs to published.
    /** @var \Drupal\entity_taxonomy\TermInterface $term */
    $term->setPublished();

    return $term;
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities) {
    $entities = parent::validateReferenceableNewEntities($entities);
    // Mirror the conditions checked in buildEntityQuery().
    if (!$this->currentUser->hasPermission('administer entity_taxonomy')) {
      $entities = array_filter($entities, function ($term) {
        /** @var \Drupal\entity_taxonomy\TermInterface $term */
        return $term->isPublished();
      });
    }
    return $entities;
  }

}
