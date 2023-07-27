<?php

namespace Drupal\frontend_editing\Controller;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFormBuilder;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\frontend_editing\ParagraphsHelperInterface;
use Drupal\paragraphs\ParagraphInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Frontend editing form.
 *
 * @package Drupal\frontend_editing\Controller
 */
class FrontendEditingController extends ControllerBase {

  /**
   * Renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Entity form builder.
   *
   * @var \Drupal\Core\Entity\EntityFormBuilder
   */
  protected $builder;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * The paragraphs helper.
   *
   * @var \Drupal\frontend_editing\ParagraphsHelperInterface
   */
  protected $paragraphsHelper;

  /**
   * FrontendEditingController constructor.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   Renderer service.
   * @param \Drupal\Core\Entity\EntityFormBuilder $builder
   *   Entity form builder.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\frontend_editing\ParagraphsHelperInterface $paragraphs_helper
   *   The paragraphs helper.
   */
  public function __construct(RendererInterface $renderer, EntityFormBuilder $builder, EntityRepositoryInterface $entity_repository, ParagraphsHelperInterface $paragraphs_helper) {
    $this->renderer = $renderer;
    $this->builder = $builder;
    $this->entityRepository = $entity_repository;
    $this->paragraphsHelper = $paragraphs_helper;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('renderer'),
      $container->get('entity.form_builder'),
      $container->get('entity.repository'),
      $container->get('frontend_editing.paragraphs_helper')
    );
  }

  /**
   * Implements form load request handler.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param string $type
   *   Entity type.
   * @param int $id
   *   Entity id.
   * @param string $display
   *   Form operation.
   *
   * @return array
   *   Form array.
   */
  public function getForm(Request $request, $type, $id, $display = 'default') {
    // Load the form and render.
    try {
      $storage = $this->entityTypeManager()->getStorage($type);
    }
    catch (PluginNotFoundException $exception) {
      $this->messenger()->addError($exception->getMessage());
      return [];
    }
    $entity = $storage->load($id);
    if (!$entity) {
      $this->messenger()->addWarning($this->t('Entity of type @type and id @id was not found',
        ['@type' => $type, '@id' => $id]
      ));
      return [];
    }

    // If the entity type is translatable, ensure we use the proper entity
    // translation for the current context, so that the access check is made on
    // the entity translation.
    $entity = $this->entityRepository->getTranslationFromContext($entity);
    $form_state_additions = [];
    switch ($display) {
      case 'default':
        if (!$entity->access('update')) {
          throw new AccessDeniedHttpException();
        }

        if ($entity instanceof ParagraphInterface) {
          $display = 'entity_edit';

          // Paragraphs cannot be saved through frontend editing when before the
          // save the user has interacted with the form in a way that it was
          // cached - e.g. by using AJAX to exchange an element or to add a new
          // element. An example is a block reference paragraph, where when
          // selecting a new reference from the select list an Ajax request will
          // be triggered.
          //
          // On submitting the form the cached form object will be used for
          // further processing. The problem is that the cached form object
          // (ParagraphEditForm) does not have the class property $root_parent
          // set as this is set only when accessing the form through the route
          // “paragraphs_edit.edit_form”, however the current implementation of
          // Frontend Editing only uses that route to submit the form to
          // (manipulates the form action before returning the form). AJAX
          // interactions with the form however go through the route
          // “xi_frontend_editing.form”, which misses the route parameter
          // “root_parent” and then the form object is cached without the
          // corresponding class property being set. The AJAX interactions are
          // routed through “xi_frontend_editing.form” because the paragraph
          // form is retrieved initially from that route and the AJAX system
          // uses the current route when building the ajax elements.
          // @see \Drupal\Core\Render\Element\RenderElement::preRenderAjaxForm()
          //
          // One solution is to ensure that the Frontend Editing passes the host
          // entity to the form build args when retrieving the form for the
          // paragraph. This however is still not a perfect solution, as the
          // “xi_frontend_editing.form” route will further be used for form
          // interactions, but the form will be routed somewhere else for
          // submission.
          $root_parent = $this->paragraphsHelper->getParagraphRootParent($entity);
          $form_state_additions = ['build_info' => ['args' => ['root_parent' => $root_parent]]];

          $url = Url::fromRoute('paragraphs_edit.edit_form', [
            'root_parent_type' => $root_parent->getEntityTypeId(),
            'root_parent' => $root_parent->id(),
            'paragraph' => $entity->id(),
          ]);
        }
        else {
          $url = Url::fromRoute('entity.' . $type . '.edit_form', [$type => $id]);
        }

        $entityForm = $this->builder->getForm($entity, $display, $form_state_additions);
        $entityForm['#action'] = $url->toString();

        $delete_url = Url::fromRoute('frontend_editing.form', [
          'id' => $entity->id(),
          'type' => $entity->getEntityTypeId(),
          'display' => 'delete',
        ]);
        $entityForm['actions']['delete'] = [
          '#type' => 'link',
          '#title' => $this->t('Delete'),
          '#url' => $delete_url,
          '#access' => $entity->isNew() || $entity->access('delete'),
          '#attributes' => [
            'class' => ['button', 'button--danger'],
          ],
          '#weight' => 10,
        ];
        break;

      case 'delete':
        if (!$entity->access('delete')) {
          throw new AccessDeniedHttpException();
        }
        if ($entity instanceof ParagraphInterface) {
          $display = 'entity_delete';
          $root_parent = $this->paragraphsHelper->getParagraphRootParent($entity);
          $form_state_additions['build_info']['args']['root_parent'] = $root_parent;

          $url = Url::fromRoute('paragraphs_edit.delete_form', [
            'root_parent_type' => $root_parent->getEntityTypeId(),
            'root_parent' => $root_parent->id(),
            'paragraph' => $entity->id(),
          ]);
        }
        else {
          $url = Url::fromRoute('entity.' . $type . '.edit_form', [$type => $id]);
        }
        $entityForm = $this->builder->getForm($entity, $display, $form_state_additions);
        $entityForm['title'] = [
          '#markup' => '<h3>' . $this->t('Are you sure you want to delete this @type?', ['@type' => $entity->getEntityType()->getSingularLabel()]) . '</h3>',
          '#weight' => -10,
        ];
        $entityForm['#action'] = $url->toString();
        break;
    }

    if (!empty($entityForm['actions']['submit'])) {
      $entityForm['actions']['submit']['#attributes']['class'][] = 'use-ajax-submit';
      $entityForm['#attached']['library'][] = 'core/drupal.ajax';
      $entityForm['#attached']['library'][] = 'frontend_editing/jquery.form';
    }
    $entityForm['#attached']['library'][] = 'frontend_editing/frontend_editing';
    return $entityForm;
  }

  /**
   * Checks access to move paragraph up request.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to move.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessUp(ParagraphInterface $paragraph) {
    return $this->paragraphsHelper->allowUp($paragraph);
  }

  /**
   * Checks access to move paragraph down request.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to move.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessDown(ParagraphInterface $paragraph) {
    return $this->paragraphsHelper->allowDown($paragraph);
  }

  /**
   * Shift up a single paragraph.
   */
  public function up(ParagraphInterface $paragraph) {
    if (!$this->paragraphsHelper->move($paragraph, 'up')) {
      $this->messenger()->addError($this->t('The paragraph could not be moved up.'));
    }
    return new RedirectResponse($this->paragraphsHelper->getRedirectUrl($paragraph)->toString());
  }

  /**
   * Shift down a single paragraph.
   */
  public function down(ParagraphInterface $paragraph) {
    if (!$this->paragraphsHelper->move($paragraph, 'down')) {
      $this->messenger()->addError($this->t('The paragraph could not be moved down.'));
    }
    return new RedirectResponse($this->paragraphsHelper->getRedirectUrl($paragraph)->toString());
  }

}
