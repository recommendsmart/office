<?php

namespace Drupal\paragraphs_table\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\field_group\FormatterHelper;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class InsertComponentForm.
 *
 * Builds the form for inserting a new component.
 */
class ParagraphAddForm extends ContentEntityForm {

  /**
   * DOM element selector.
   *
   * @var string
   */
  protected $domSelector;

  /**
   * The paragraph.
   *
   * @var \Drupal\paragraphs\Entity\Paragraph
   */
  protected $entity;

  /**
   * Entity.
   *
   * @var object
   */
  protected $host;

  /**
   * Entity field name.
   *
   * @var string
   */
  protected $hostField;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The current Request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $requestStack;

  /**
   * The entity being cloned by this form.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $originalEntity;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, ModuleHandlerInterface $module_handler, EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, Request $request) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->setModuleHandler($module_handler);
    $this->routeMatch = $route_match;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request;
    $paragraph_type = $route_match->getParameter('paragraph_type');
    if (!empty($paragraph_type)) {
      $this->setParagraph($this->newParagraph($paragraph_type));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('request_stack')->getCurrentRequest()
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getParagraph() {
    return $this->entity;
  }

  /**
   * {@inheritDoc}
   */
  public function setParagraph(Paragraph $paragraph) {
    $this->entity = $paragraph;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'paragraphs_table_add';
  }

  /**
   * {@inheritdoc}
   */
  protected function prepareEntity() {
    parent::prepareEntity();

    $account = $this->currentUser();

    // Keep track of the original entity.
    $this->originalEntity = $this->entity;

    // Create a duplicate.
    $paragraph = $this->entity = $this->entity->createDuplicate();
    $paragraph->set('created', $this->time->getRequestTime());
    $paragraph->setOwnerId($account->id());
    $paragraph->setRevisionAuthorId($account->id());
  }

  /**
   * {@inheritDoc}
   *
   * @param array $form
   *   The form arrays.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The layout paragraphs layout object.
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The paragraph entity.
   * @param string $host_type
   *   The entity types.
   * @param string $host_field
   *   The entity field.
   * @param int $host_id
   *   The id of the entity.
   *
   * @return array
   *   Return form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    Paragraph $paragraph = NULL,
    string $host_type = NULL,
    string $host_field = NULL,
    int $host_id = NULL
  ) {
    if (!$form_state->has('entity_form_initialized')) {
      $this->init($form_state);
    }
    $this->host = $this->entityTypeManager->getStorage($host_type)->load($host_id);
    $this->entity = $paragraph;
    $this->hostField = $host_field;
    $param = [$paragraph->getEntityTypeId(), $host_type, $host_field, $host_id];
    $this->domSelector = HTML::getId(implode('-', $param));
    return $this->buildComponentForm($form, $form_state);
  }

  /**
   * Create the form title.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The form title.
   */
  protected function formTitle() {
    return $this->t('Create new @type', [
      '@type' => $this->entity->getParagraphType()
        ->label(),
    ]);
  }

  /**
   * Builds a component (paragraph) edit form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   * @param bool $formatTable
   *   Display form with table format.
   *
   * @return array
   *   Return form.
   */
  protected function buildComponentForm(array $form, FormStateInterface $form_state, $formatTable = TRUE) {
    $form_mode = 'default';
    $display = EntityFormDisplay::collectRenderDisplay($this->entity, $form_mode);
    $form_state->set('form_display', $display);
    $display->buildForm($this->entity, $form, $form_state);
    $formatTable = $this->requestStack->get('formatTable');
    if ($formatTable) {
      $fields = [];
      $header = [];
      foreach (Element::children($form) as $fieldName) {
        $field = $form[$fieldName];
        _paragraphs_table_hidden_label($field);
        $fields[$fieldName] = $field;
      }
      $weight = array_column($fields, '#weight');
      array_multisort($weight, SORT_ASC, $fields);
      $form['paragraphTableAdd'] = [
        '#type' => 'table',
        '#title' => $this->formTitle(),
      ];
      foreach ($fields as $fieldName => $field) {
        $headerTitle = !empty($field['widget']['#title']) ? $field['widget']['#title'] : '';
        if (empty($headerTitle)) {
          $headerTitle = !empty($field["widget"][0]["#title"]) ? $field["widget"][0]["#title"] : '';
          if (empty($headerTitle)) {
            $headerTitle = !empty($field["widget"][0]["value"]["#title"]) ? $field["widget"][0]["value"]["#title"] : '';
          }
        }
        if (empty($headerTitle) && !empty($field[0]['#title'])) {
          $headerTitle = $field[0]['#title'];
        }
        if (empty($headerTitle) && !empty($field["widget"]['target_id']['#title'])) {
          $headerTitle = $field["widget"]['target_id']['#title'];
        }
        $header[$fieldName] = $headerTitle;
        $form['paragraphTableAdd'][0][$fieldName] = $field;
        unset($form[$fieldName]);
      }
      $form['paragraphTableAdd']['#header'] = $header;
    }

    $this->paragraphType = $this->entity->getParagraphType();

    $form_state->set('form_display', $display);

    $form += [
      '#title' => $this->formTitle(),
      '#display' => $display,
      '#paragraph' => $this->entity,
      'actions' => [
        '#weight' => 100,
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#weight' => 100,
          '#value' => $this->t('Save'),
          '#attributes' => [
            'class' => ['btn', 'btn-success', 'btn--save'],
            'data-disable-refocus' => 'true',
          ],
        ],
        'cancel' => [
          '#type' => 'button',
          '#weight' => 200,
          '#value' => $this->t('Cancel'),
          '#attributes' => [
            'class' => [
              'dialog-cancel',
              'btn',
              'btn-danger',
              'btn--cancel',
            ],
            'onClick' => 'jQuery(this).closest("form").remove(); event.preventDefault();',
          ],
        ],
      ],
    ];

    // Support for Field Group module based on Paragraphs module.
    // @todo Remove as part of https://www.drupal.org/node/2640056
    if ($this->moduleHandler->moduleExists('field_group')) {
      $context = [
        'entity_type' => $this->entity->getEntityTypeId(),
        'bundle' => $this->entity->bundle(),
        'entity' => $this->entity,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $display->getMode(),
      ];
      // phpcs:ignore
      field_group_attach_groups($form, $context);
      if (method_exists(FormatterHelper::class, 'formProcess')) {
        $form['#process'][] = [FormatterHelper::class, 'formProcess'];
      }
      elseif (function_exists('field_group_form_pre_render')) {
        $form['#pre_render'][] = 'field_group_form_pre_render';
      }
      elseif (function_exists('field_group_form_process')) {
        $form['#process'][] = 'field_group_form_process';
      }
    }
    return $form;
  }

  /**
   * Validate the component form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

    // Validate the paragraph with submitted form values.
    $paragraph = $this->buildParagraphComponent($form, $form_state);
    $violations = $paragraph->validate();
    // Remove violations of inaccessible fields.
    $violations->filterByFieldAccess($this->currentUser());
    // The paragraph component was validated.
    $paragraph->setValidationRequired(FALSE);
    // Flag entity level violations.
    foreach ($violations->getEntityViolations() as $violation) {
      /** @var \Symfony\Component\Validator\ConstraintViolationInterface $violation */
      $form_state->setErrorByName('', $violation->getMessage());
    }
    $form['#display']->flagWidgetsErrorsFromViolations($violations, $form, $form_state);

  }

  /**
   * Saves the paragraph component.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $paragraph = $this->buildParagraphComponent($form, $form_state);
    $paragraph->save();
    $this->host->get($this->hostField)->appendItem($paragraph);
    $this->host->save();
    $request = $this->requestStack;
    if (!empty($request->query) && $request->query->has('destination')) {
      $destination = $request->query->get('destination');
      if (strpos($destination, '/') !== 0) {
        $destination = '/' . $destination;
      }
      $url = Url::fromUserInput($destination);
      $request->query->remove('destination');
      $form_state->setRedirectUrl($url);
    }
    else {
      $form_state->setRedirectUrl($this->host->toUrl());
    }
  }

  /**
   * Builds the paragraph component using submitted form values.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return \Drupal\paragraphs\Entity\Paragraph
   *   The paragraph entity.
   */
  public function buildParagraphComponent(array $form, FormStateInterface $form_state) {
    /** @var Drupal\Core\Entity\Entity\EntityFormDisplay $display */
    $display = $form['#display'];

    $paragraph = clone $this->entity;
    $paragraph->getAllBehaviorSettings();
    $paragraph->setNeedsSave(TRUE);
    $display->extractFormValues($paragraph, $form, $form_state);
    return $paragraph;
  }

  /**
   * Creates a new, empty paragraph empty of the provided type.
   *
   * @param \Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The new paragraph.
   */
  protected function newParagraph(ParagraphsTypeInterface $paragraph_type) {
    $entity_type = $this->entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
    return $paragraph;
  }

}
