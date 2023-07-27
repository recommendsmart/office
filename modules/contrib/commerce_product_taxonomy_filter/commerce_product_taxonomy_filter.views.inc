<?php

/**
 * @file
 * Provides views data for taxonomy.module.
 */

use Drupal\field\FieldStorageConfigInterface;

/**
 * Implements hook_views_data_alter().
 */
function commerce_product_taxonomy_filter_views_data_alter(&$data) {
  $data['commerce_product_field_data']['term_commerce_product_tid'] = [
    'title' => t('Taxonomy terms on commerce_product'),
    'help' => t('Relate commerce_products to taxonomy terms, specifying which vocabulary or vocabularies to use. This relationship will cause duplicated records if there are multiple terms.'),
    'relationship' => [
      'id' => 'commerce_product_term_data',
      'label' => t('term'),
      'base' => 'taxonomy_term_field_data',
    ],
    'field' => [
      'title' => t('All taxonomy terms'),
      'help' => t('Display all taxonomy terms associated with a commerce_product from specified vocabularies.'),
      'id' => 'commerce_product_taxonomy_index_tid',
      'no group by' => TRUE,
      'click sortable' => FALSE,
    ],
  ];
  $data['commerce_product_taxonomy_index']['table']['join'] = [
    'taxonomy_term_field_data' => [
      // Links directly to taxonomy_term_field_data via tid.
      'left_field' => 'tid',
      'field' => 'tid',
    ],
    'commerce_product_field_data' => [
      // Links directly to commerce_product via product_id.
      'left_field' => 'product_id',
      'field' => 'product_id',
    ],
    'taxonomy_term__parent' => [
      'left_field' => 'entity_id',
      'field' => 'tid',
    ],
  ];
  $data['commerce_product_taxonomy_index']['tid'] = [
    'group' => t('Product'),
    'title' => t('Has taxonomy term ID'),
    'help' => t('Display content if it has the selected taxonomy terms.'),
    'argument' => [
      'id' => 'taxonomy_index_tid',
      'name table' => 'taxonomy_term_field_data',
      'name field' => 'name',
      'empty field name' => t('Uncategorized'),
      'numeric' => TRUE,
      'skip base' => 'taxonomy_term_field_data',
    ],
    'filter' => [
      'title' => t('Has taxonomy term'),
      'id' => 'taxonomy_index_tid',
      'hierarchy table' => 'taxonomy_term__parent',
      'numeric' => TRUE,
      'skip base' => 'taxonomy_term_field_data',
      'allow empty' => TRUE,
    ],
  ];
  $data['commerce_product_field_data']['term_commerce_product_tid_depth'] = [
    'help' => t('Display content if it has the selected taxonomy terms, or children of the selected terms. Due to additional complexity, this has fewer options than the versions without depth.'),
    'real field' => 'product_id',
    'argument' => [
      'title' => t('Has taxonomy term ID (with depth)'),
      'id' => 'commerce_product_taxonomy_index_tid_depth',
      'accept depth modifier' => TRUE,
    ],
    'filter' => [
      'title' => t('Has taxonomy terms (with depth)'),
      'id' => 'commerce_product_taxonomy_index_tid_depth',
    ],
  ];

  $data['commerce_product_field_data']['term_commerce_product_tid_depth_modifier'] = [
    'title' => t('Has taxonomy term ID depth modifier'),
    'help' => t('Allows the "depth" for Taxonomy: Term ID (with depth) to be modified via an additional contextual filter value.'),
    'argument' => [
      'id' => 'commerce_product_taxonomy_index_tid_depth_modifier',
    ],
  ];
}

/**
 * Implements hook_field_views_data_alter().
 *
 * Views integration for entity reference fields which reference taxonomy terms.
 * Adds a term relationship to the default field data.
 *
 * @see views_field_default_views_data()
 */
function commerce_product_taxonomy_filter_field_views_data_alter(array &$data, FieldStorageConfigInterface $field_storage) {
  if ($field_storage->getType() == 'entity_reference' && $field_storage->getSetting('target_type') == 'taxonomy_term') {
    foreach ($data as $table_name => $table_data) {
      foreach ($table_data as $field_name => $field_data) {
        if (isset($field_data['filter']) && $field_name != 'delta') {
          $data[$table_name][$field_name]['filter']['id'] = 'commerce_product_taxonomy_index_tid';
        }
      }
    }
  }
}

/**
 * Implements hook_views_plugins_argument_validator_alter().
 *
 * Extend the generic entity argument validator.
 *
 * @see \Drupal\views\Plugin\views\argument_validator\Entity
 */
function commerce_product_taxonomy_filter_views_plugins_argument_validator_alter(array &$plugins) {
  $plugins['entity:taxonomy_term']['title'] = t('Taxonomy term ID');
  $plugins['entity:taxonomy_term']['class'] = 'Drupal\commerce_product_taxonomy_filter\Plugin\views\argument_validator\Term';
  $plugins['entity:taxonomy_term']['provider'] = 'taxonomy';
}
