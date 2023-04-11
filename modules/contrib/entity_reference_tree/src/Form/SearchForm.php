<?php

namespace Drupal\entity_reference_tree\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\entity_reference_tree\Ajax\CloseEntityReferenceTreeModalDialogCommand;

/**
 * ModalForm class.
 *
 * To properly inject services, override create() and use the setters provided
 * by the traits to inject the needed services.
 *
 * @code
 * public static function create($container) {
 *   $form = new static();
 *   // In this example we only need string translation so we use the
 *   // setStringTranslation() method provided by StringTranslationTrait.
 *   $form->setStringTranslation($container->get('string_translation'));
 *   return $form;
 * }
 * @endcode
 */
class SearchForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_reference_tree_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $field_edit_id = '', $bundles = '', $entity_type = '', $theme = 'default', $dots = false) {
    // Do nothing after the form is submitted.
    if (!empty($form_state->getValues())) {
      return [];
    }
    // Limit number of selected nodes of tree.
    $limit = $this->getRequest()->get('limit');

    // The status messages that will contain any form errors.
    $form['status_messages'] = [
      '#type' => 'status_messages',
      '#weight' => -10,
    ];

    // Selected entity text.
    $form['selected_text'] = [
        '#type' => 'html_tag',
        '#tag' => 'div',
        '#value' => $this
        ->t('Selected Entities'),
        '#weight' => 1000,
        '#attributes' => [
            'class' => [
                'selected-entities-text',
            ],
            'id' => [
                'entity-reference-tree-selected-text',
            ],
        ],
    ];
    // Hidden field for submitting selected entity IDs.
    $form['selected_node'] = [
      '#type' => 'hidden',
      '#attributes' => [
        'id' => [
          'entity-reference-tree-selected-node',
        ],
      ],
    ];
    // Search filter box.
    $form['tree_search'] = [
      '#type' => 'textfield',
      '#title' => $this
        ->t('Search'),
      '#size' => 60,
      '#attributes' => [
        'id' => [
          'entity-reference-tree-search',
        ],
      ],
    ];
    // JsTree container.
    $form['tree_container'] = [
      '#type' => 'html_tag',
      '#tag' => 'div',
      '#attributes' => [
        'id' => [
          'entity-reference-tree-wrapper',
        ],
        'theme' => $theme,
        'dots' => $dots, 
      ],
    ];
    // Submit button.
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitForm'],
        'event' => 'click',
      ],
    ];

    $form['#attached']['library'][] = 'entity_reference_tree/jstree';
    $form['#attached']['library'][] = 'entity_reference_tree/entity_tree';
    $form['#attached']['library'][] = 'entity_reference_tree/jstree_' . $theme . '_theme';

    // Disable the cache for this form.
    $form_state->setCached(FALSE);
    $form['#cache'] = ['max-age' => 0];
    $form['#attributes']['data-user-info-from-browser'] = FALSE;
    // Field element id.
    $form['field_id'] = [
      '#name' => 'field_id',
      '#type' => 'hidden',
      '#weight' => 80,
      '#value' => $field_edit_id,
      '#attributes' => [
        'id' => [
          'entity-reference-tree-widget-field',
        ],
      ],
    ];
    // Entity type.
    $form['entity_type'] = [
      '#name' => 'entity_type',
      '#type' => 'hidden',
      '#weight' => 80,
      '#value' => $entity_type,
      '#attributes' => [
        'id' => [
          'entity-reference-tree-entity-type',
        ],
      ],
    ];
    // Entity bundle.
    $form['entity_bundle'] = [
      '#name' => 'entity_bundle',
      '#type' => 'hidden',
      '#weight' => 80,
      '#value' => $bundles,
      '#attributes' => [
        'id' => [
          'entity-reference-tree-entity-bundle',
        ],
      ],
    ];
    
    // Pass data to js file.
    $form['#attached']['drupalSettings'] = [
        'entity_tree_token_' . $field_edit_id => \Drupal::csrfToken()->get($bundles),
        'tree_limit_' . $field_edit_id => empty($limit) ? -1 : $limit, 
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#entity_reference_tree_wrapper', $form));
    }
    else {
      $response->addCommand(new InvokeCommand(NULL, 'entitySearchDialogAjaxCallback', [$form_state->getValue('field_id'), $form_state->getValue('selected_node')]));
      $response->addCommand(new CloseEntityReferenceTreeModalDialogCommand());
    }

    return $response;
  }

}
