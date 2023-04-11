<?php

namespace Drupal\datafield\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides delete form.
 *
 * @internal
 */
class DeleteFieldForm extends FormBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(RequestStack $request_stack) {
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_data_field';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, EntityInterface $entity = NULL, $field_name = NULL, $delta = 0) {
    $form_state->set('entity', $entity);
    $form_state->set('field_name', $field_name);
    $form_state->set('delta', $delta);

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete'),
      '#button_type' => 'danger',
    ];
    $query = $this->requestStack->getCurrentRequest()->query;
    $url = NULL;
    if ($query->has('destination')) {
      $options = UrlHelper::parse($query->get('destination'));
      try {
        $url = Url::fromUserInput('/' . ltrim($options['path'], '/'), $options);
      }
      catch (\InvalidArgumentException $e) {
        // Suppress the exception and fall back to the form's cancel url.
      }
    }
    if (!$url) {
      $url = $entity->toUrl();
    }
    $form['actions']['cancel'] = [
      '#type' => 'link',
      '#title' => $this->t('Cancel'),
      '#attributes' => ['class' => ['button', 'dialog-cancel']],
      '#url' => $url,
      '#cache' => [
        'contexts' => [
          'url.query_args:destination',
        ],
      ],
    ];

    $item = $field_name . ' #' . $delta;
    $form['#title'] = $this->t('Are you sure you want to delete this @item?', ['@item' => $item]);
    $form['#attributes']['class'][] = 'confirmation';
    $form['description'] = ['#markup' => $this->t('This action cannot be undone.')];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
    $entity = $form_state->get('entity');
    $field_name = $form_state->get('field_name');
    $delta = $form_state->get('delta');
    $entity->get($field_name)->removeItem($delta);
    $entity->save();
    $form_state->setRedirectUrl($entity->toUrl('canonical'));
  }

}
