<?php

namespace Drupal\entity_reference_tree\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Drupal\entity_reference_tree\Ajax\OpenEntityReferenceTreeModalDialogCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Ajax\AjaxResponse;

/**
 * EntityReferenceTreeController class.
 */
class EntityReferenceTreeController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;
  
  /**
   * CSRF Token.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The EntityReferenceTreeController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder, CsrfTokenGenerator $csrfToken) {
    $this->formBuilder = $formBuilder;
    $this->csrfToken = $csrfToken;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('form_builder'),
        $container->get('csrf_token')
        );
  }

  /**
   * Callback for opening the modal form.
   */
  public function openSearchForm(Request $request, string $field_edit_id, string $bundle, string $entity_type, string $theme, int $dots, string $dialog_title) {
    $response = new AjaxResponse();
    // Translate the title.
    $dialog_title = $this->t('@title', ['@title' => $dialog_title]);

    // Get the modal form using the form builder.
    $modal_form = $this->formBuilder->getForm('Drupal\entity_reference_tree\Form\SearchForm', $field_edit_id, $bundle, $entity_type, $theme, $dots);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenEntityReferenceTreeModalDialogCommand($dialog_title, $modal_form, ['width' => '800']));

    return $response;
  }

  /**
   * Callback for JsTree json data.
   */
  public function treeJson(Request $request, string $entity_type, string $bundles) {
    $token = $request->get('token');
    
    if (empty($token) || !$this->csrfToken->validate($token, $bundles)) {
      return new Response($this->t('Access denied!'));
    }

    // Instance a entity tree builder for this entity type if it exists.
    if (\Drupal::hasService('entity_reference_' . $entity_type . '_tree_builder')) {
      $treeBuilder = \Drupal::service('entity_reference_' . $entity_type . '_tree_builder');
    }
    else {
      // Todo: A basic entity tree builder.
      $treeBuilder = \Drupal::service('entity_reference_entity_tree_builder');
    }

    $bundlesAry = explode(',', $bundles);
    $entityTrees = [];
    $entityNodeAry = [];

    foreach ($bundlesAry as $bundle_id) {
      $tree = $treeBuilder->loadTree($entity_type, $bundle_id);
      if (!empty($tree)) {
        $entityTrees[] = $tree;
      }
    }

    foreach ($entityTrees as $tree) {
      foreach ($tree as $entity) {
        // Create tree node for each entity.
        // Store them into an array passed to JS.
        // An array in JavaScript is indexed list.
        // JavaScript's array indices are always sequential
        // and start from 0.
        $treeNode = $treeBuilder->createTreeNode($entity);
        // Applies a very permissive XSS/HTML filter for node text.
        $treeNode['text'] = Xss::filterAdmin($treeNode['text']);
        $entityNodeAry[] = $treeNode;
      }
    }

    return new JsonResponse($entityNodeAry);
  }

}
