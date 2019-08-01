<?php

namespace Drupal\if_then_else\core;

use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Form\FormInterface;

/**
 * Class defined to have common functions for ifthenelse rules processing.
 */
class IfthenelseUtilities {

  /**
   * Check if form class is valid.
   *
   * @param string $form_class
   *   Form Class name to be validated.
   *
   * @return bool
   *   Return if form class is valid or not.
   */
  public static function validateFormClass($form_class) {
    if (is_string($form_class) && class_exists($form_class)) {
      // Generating class object from class string name to compare
      // if it is instance of FormInterface.
      $other_form_class = \Drupal::classResolver($form_class);
      if (is_object($other_form_class) && ($other_form_class instanceof FormInterface)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Get content entities and bundles.
   *
   * @return array
   *   Return array of Content entities and their bundles
   */
  public function getContentEntitiesAndBundles() {
    static $content_entity_types = [];

    if (empty($content_entity_types)) {
      // Fetching all entities.
      $entity_type_definitions = \Drupal::entityTypeManager()->getDefinitions();
      $bundle_info = \Drupal::service("entity_type.bundle.info")->getAllBundleInfo();
      /* @var $definition EntityTypeInterface */
      foreach ($entity_type_definitions as $definition) {

        // Checking if the entity is of type content.
        if ($definition instanceof ContentEntityType) {
          $entity_id = $definition->id();

          $content_entity_types[$entity_id]['entity_id'] = $entity_id;
          $content_entity_types[$entity_id]['label'] = $definition->getLabel()->__toString();
          // Fetching all bundles of entity.
          $entity_bundles = $bundle_info[$entity_id];

          // Getting label of each bundle of an entity.
          foreach ($entity_bundles as $bundle_id => $bundle) {
            if (is_object($bundle['label'])) {
              $content_entity_types[$entity_id]['bundles'][$bundle_id]['label'] = $bundle['label']->__toString();
            }
            elseif (!is_object($bundle['label']) && !is_array($bundle['label'])) {
              $content_entity_types[$entity_id]['bundles'][$bundle_id]['label'] = $bundle['label'];
            }
            $content_entity_types[$entity_id]['bundles'][$bundle_id]['bundle_id'] = $bundle_id;
          }
        }
      }
    }

    return $content_entity_types;
  }

  /**
   * Get list of fields by entity and bundle id.
   *
   * @param array $content_entity_types
   *   List of content entities and bundles.
   *
   * @return array
   *   List of fields associated with bundle.
   */
  public function getFieldsByEntityBundleId(array $content_entity_types, $return_type = 'field') {
    static $listFields = [];
    static $field_type = [];

    if (empty($listFields)) {
      $entity_field_manager = \Drupal::service('entity_field.manager');

      foreach ($content_entity_types as $entity) {
        $entity_id = $entity['entity_id'];
        foreach ($entity['bundles'] as $bundle_id => $bundle) {
          $fields = $entity_field_manager->getFieldDefinitions($entity_id, $bundle_id);
          foreach ($fields as $field_name => $field_definition) {
            if (!empty($field_definition->getTargetBundle() || $field_name == 'title')) {
              // List of all fields in an entity bundle.
              $listFields[$field_name]['name'] = $field_definition->getLabel();
              if (is_object($listFields[$field_name]['name'])) {
                $listFields[$field_name]['name'] = $listFields[$field_name]['name']->__toString();
              }
              $listFields[$field_name]['code'] = $field_name;
              $listFields[$field_name]['entity_bundle']['entity'][$entity_id] = ['code' => $entity_id, 'name' => $entity['label']];
              $listFields[$field_name]['entity_bundle'][$entity_id]['bundle'][] = ['code' => $bundle_id, 'name' => $bundle['label']];

              $field_type[$entity_id][$field_name] = $field_definition->getType();
            }
          }
        }
      }

      // Converting it to non associative array for working with
      // Vuejs multiselect.
      $listFieldsAssoc = $listFields;
      $listFields = [];
      $i = 0;
      foreach ($listFieldsAssoc as $field) {
        if($field['name'] == 'Menu link title'){
          $field['name'] = 'Title';
        }
        $listFields[$i]['name'] = $field['name'];
        $listFields[$i]['code'] = $field['code'];
        $listFields[$i]['entity_bundle'] = $field['entity_bundle'];
        $k = 0;
        foreach ($listFields[$i]['entity_bundle']['entity'] as $ekey => $entity) {
          $listFields[$i]['entity_bundle']['entity'][$k] = $entity;
          unset($listFields[$i]['entity_bundle']['entity'][$ekey]);
          $k++;
        }
        $i++;
      }
    }

    if ($return_type == 'field') {
      return $listFields;
    }
    elseif ($return_type == 'field_type') {
      return $field_type;
    }
  }

  /**
   * Get Entity and Bundle list by Field name.
   */
  public function getEntityByFieldName($fields) {

    foreach ($fields as $field) {
      $fieldentity[$field['code']] = $field['entity_bundle'];
    }
    return $fieldentity;
  }

  /**
   * Get a specific field by entity, bundle id and field id.
   *
   * @param string $entity_id
   *   Content Entity id.
   * @param string $bundle_id
   *   Bundle id whose fields to be fetched.
   * @param string $field_name
   *   Field name.
   *
   * @return array
   *   Field definition and info for a specific field.
   */
  public function getFieldInfoByEntityBundleId($entity_id, $bundle_id, $field_name) {

    // Get field definitation.
    $field = FieldConfig::loadByName($entity_id, $bundle_id, $field_name);
    $field_type = $field->getType();

    // List text type of field.
    if ($field_type == 'list_string') {
      $field_settings = $field->getFieldStorageDefinition()->getSettings();
      foreach ($field_settings['allowed_values'] as $key => $value) {
        $field_value[] = [
          'key' => $key,
          'name' => $value,
        ];
      }
      $field_type = 'select-input';
      $field_label = $field->getLabel();
    }

    // Plain text type of field.
    if ($field_type == 'string') {
      $field_value = '';
      $field_type = 'text-input';
      $field_label = $field->getLabel();
    }

    // Plain text type of field.
    if ($field_type == 'text_with_summary') {
      $field_value = '';
      $field_type = 'textarea-input';
      $field_label = $field->getLabel();
    }

    // Date field.
    if ($field_type == 'datetime') {
      $field_value = '';
      $field_type = 'textdate-input';
      $field_label = $field->getLabel();
    }

    // Entity reference field.
    if ($field_type == 'entity_reference') {
      $target_entity = $field->getSettings()['target_type'];
      $bundles = $field->getSettings()['handler_settings']['target_bundles'];

      $list_query = \Drupal::entityQuery($target_entity)
        ->condition('status', 1);
      if ($target_entity == 'taxonomy_term') {
        $list_query->condition('vid', $bundles, "IN");
      }
      elseif ($target_entity == 'node') {
        $list_query->condition('type', $bundles, 'IN');
      }

      $nids = $list_query->execute();

      $entities = \Drupal::entityTypeManager()->getStorage($target_entity)->loadMultiple($nids);
      $field_value = [];
      $i = 0;
      foreach ($entities as $entity) {
        $field_value[$i]['key'] = $entity->id();
        if ($target_entity == 'taxonomy_term') {
          $field_value[$i]['name'] = $entity->getName();
        }
        elseif ($target_entity == 'node') {
          $field_value[$i]['name'] = $entity->getTitle();
        }
        elseif ($target_entity == 'user') {
          $field_value[$i]['name'] = $entity->getAccountName();
        }
        $i++;
      }

      if ($target_entity == 'node') {
        $field_type = 'contentreference-input';
      }
      elseif ($target_entity == 'taxonomy_term') {
        $field_type = 'taxonomyreference-input';
      }
      elseif ($target_entity == 'user') {
        $field_type = 'userreference-input';
      }
      $field_label = $field->getLabel();
    }

    // Boolean type of field.
    if ($field_type == 'boolean') {
      $field_value = '';
      $field_type = 'boolean-input';
      $field_label = $field->getLabel();
    }

    // Boolean type of field.
    if ($field_type == 'email') {
      $field_value = '';
      $field_type = 'email-input';
      $field_label = $field->getLabel();
    }

    // Boolean type of field.
    if ($field_type == 'link') {
      $field_value = '';
      $field_type = 'text-input';
      $field_label = $field->getLabel();
    }

    $field_info['type'] = $field_type;
    $field_info['field_name'] = $field_name;
    $field_info['value'] = $field_value;
    $field_info['field_label'] = $field_label;

    // Cardinality of field.
    $cardinality = $field->getFieldStorageDefinition()->getCardinality();
    $field_info['cardinality'] = $cardinality;

    return $field_info;
  }

  /**
   * Get form fields by form class.
   *
   * @param string $form_class
   *   Form class name which extends FormInterface class.
   *
   * @return array
   *   List of fields in form.
   */
  public function getFieldsByFormClass($form_class) {
    $listFields = [];
    if (is_string($form_class) && class_exists($form_class)) {
      // Generating class object from class string name to compare
      // if it is instance of FormInterface.
      $other_form_class = \Drupal::classResolver($form_class);
      if (!is_object($other_form_class) || !($other_form_class instanceof FormInterface)) {
        // @todo
        // exception if the form class entered is wrong.
      }
      else {
        $other_form = \Drupal::formBuilder()->getForm($form_class);

        // Iterate all keys of form array.
        foreach ($other_form as $field_name => $field) {
          // Skip all keys which starts with #. they are not fields.
          if (strpos($field_name, '#') === FALSE) {
            if ($field['#type'] == 'hidden' || $field['#type'] == 'token' || $field['#type'] == 'actions' || $field['#type'] == 'details' ||$field['#type'] == 'vertical_tabs') {
              // Skip all keys which can't be made required.
              continue;
            }

            if ($field['#type'] == 'container') {
              if (isset($field['widget'])) {
                foreach ($field['widget'] as $k => $value) {
                  if (strpos($k, '#') !== FALSE) {
                    // Skip all keys which have #.
                    continue;
                  }

                  // If title is translatable object.
                  if (is_object($field['widget'][$k]['#title'])) {
                    $listFields[$field_name]['name'] = $field['widget'][$k]['#title']->__toString();
                  }
                  elseif (is_string($field['widget'][$k]['#title'])) {
                    $listFields[$field_name]['name'] = $field['widget'][$k]['#title'];
                  }
                  $listFields[$field_name]['code'] = $field_name;
                }
              }
            }
            else {
              if (is_object($field['#title'])) {
                $listFields[$field_name]['name'] = $field['#title']->__toString();
              }
              elseif (is_string($field['#title'])) {
                $listFields[$field_name]['name'] = $field['#title'];
              }
              $listFields[$field_name]['code'] = $field_name;
            }
          }
        }
      }
    }

    return $listFields;
  }

  /**
   * Get views name and Display ID list.
   */
  public function getViewsNameAndDisplay() {
    $query = \Drupal::entityQuery('view')
      ->condition('status', TRUE);
    $views_ids = $query->execute();
    $views = \Drupal::entityTypeManager()->getStorage('view')->loadMultiple($views_ids);
    static $views_lists = [];
    foreach ($views as $view) {
      $views_lists[$view->id()]['id'] = $view->id();
      $views_lists[$view->id()]['label'] = $view->label();

      foreach ($view->get('display') as $dislay) {

        $views_lists[$view->id()]['display'][$dislay['id']]['id'] = $dislay['id'];
        $views_lists[$view->id()]['display'][$dislay['id']]['label'] = $dislay['display_title'];
      }
    }
    return $views_lists;
  }

}
