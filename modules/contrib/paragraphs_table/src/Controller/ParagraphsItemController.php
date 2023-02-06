<?php

namespace Drupal\paragraphs_table\Controller;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormState;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for paragraphs item routes.
 */
class ParagraphsItemController extends ControllerBase implements ContainerInjectionInterface {

  use AjaxHelperTrait;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Form builder will be used via Dependency Injection.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * CompanyController constructor.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, FormBuilderInterface $form_builder) {
    $this->entityRepository = $entity_repository;
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('form_builder')
    );
  }

  /**
   * Provides the paragraphs item submission form.
   *
   * @param \Drupal\paragraphs\Entity\ParagraphsType $paragraph_type
   *   The paragraphs entity for the paragraph item.
   * @param string $entity_type
   *   The type of the entity hosting the paragraph item.
   * @param string $entity_field
   *   Entity field store paragraphs.
   * @param int $entity_id
   *   The id of the entity hosting the paragraph item.
   *
   * @return array
   *   A paragraph item submission form.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function add(ParagraphsType $paragraph_type, $entity_type, $entity_field, $entity_id) {
    $paragraph = $this->newParagraph($paragraph_type);
    $load_form = 'Drupal\paragraphs_table\Form\ParagraphAddForm';
    $form_state = (new FormState())->addBuildInfo('args', [
      $paragraph,
      $entity_type,
      $entity_field,
      $entity_id,
    ]);
    $form = $this->formBuilder->buildForm(
      $load_form,
      $form_state,
      $paragraph,
      $entity_type,
      $entity_field,
      $entity_id,
    );

    if ($this->isAjax()) {
      $param = [$paragraph_type->id(), $entity_type, $entity_field, $entity_id];
      $id = "#" . HTML::getId(implode('-', $param));
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand($id, $form));
      return $response;
    }
    return $form;

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
    $entityTypeManager = $this->entityTypeManager();
    $entity_type = $entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $entityTypeManager->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
    return $paragraph;
  }

  /**
   * The _title_callback for the paragraphs_item.add route.
   *
   * @param \Drupal\paragraphs\Entity\ParagraphsType $paragraph_type
   *   The current paragraphs_type.
   *
   * @return string
   *   The page title.
   */
  public function addPageTitle(ParagraphsType $paragraph_type) {
    return $this->t('Create @label', ['@label' => $paragraph_type->label()]);
  }

  /**
   * Displays a paragraphs item.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The Paragraph item we are displaying.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function page(Paragraph $paragraph) {
    return $this->buildPage($paragraph);
  }

  /**
   * The _title_callback for the paragraphs_item.view route.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The current paragraphs_item.
   *
   * @return string
   *   The page title.
   */
  public function pageTitle(Paragraph $paragraph = NULL) {
    return $this->entityRepository->getTranslationFromContext($paragraph)
      ->label() . ' #' . $paragraph->id();
  }

  /**
   * Builds a paragraph item page render array.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *   The field paragraph item we are displaying.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  protected function buildPage(Paragraph $paragraph) {
    return [
      'paragraph' => $this->entityTypeManager()
        ->getViewBuilder('paragraph')
        ->view($paragraph),
    ];
  }

}
