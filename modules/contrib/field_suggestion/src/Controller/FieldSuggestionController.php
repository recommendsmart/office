<?php

namespace Drupal\field_suggestion\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\field_suggestion\Ajax\FieldSuggestionCommand;
use Drupal\field_suggestion\FieldSuggestionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FieldSuggestionController.
 */
class FieldSuggestionController extends ControllerBase {

  /**
   * Permission per action type.
   */
  const PERMISSIONS = [
    'pin' => 'pin and unpin field suggestion',
    'ignore' => 'ignore field suggestion',
  ];

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $invalidator;

  /**
   * The helper.
   *
   * @var \Drupal\field_suggestion\Service\FieldSuggestionHelperInterface
   */
  protected $helper;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The field definition.
   *
   * @var \Drupal\Core\Field\BaseFieldDefinition
   */
  protected $definition;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);

    $instance->invalidator = $container->get('cache_tags.invalidator');
    $instance->helper = $container->get('field_suggestion.helper');
    $instance->entityFieldManager = $container->get('entity_field.manager');

    return $instance;
  }

  /**
   * Provides a edit title callback.
   *
   * @param \Drupal\field_suggestion\FieldSuggestionInterface $field_suggestion
   *   The field suggestion entity object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the entity edit page.
   */
  public function title(FieldSuggestionInterface $field_suggestion) {
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions(
      $entity_type = $field_suggestion->type()
    );

    return $this->t(
      'Edit a suggestion based on the %value value of the %field field of the
%type entity type',
      [
        '%value' => $field_suggestion->label(),
        '%field' => $definitions[$field_suggestion->field()]->getLabel(),
        '%type' => $this->entityTypeManager()->getDefinition($entity_type)->getLabel(),
      ]
    );
  }

  /**
   * Choose a specific suggestion.
   *
   * @param $entity_type
   *   The entity type identifier.
   * @param $field_name
   *   The field name.
   * @param $delta
   *   The suggestion offset.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function select($entity_type, $field_name, $delta) {
    return (new AjaxResponse())->addCommand(new FieldSuggestionCommand(
      $field_name,
      $delta,
      $this->helper->encode($this->property($entity_type, $field_name))
    ));
  }

  /**
   * Access check based on whether a field is supported or not.
   *
   * @param string $entity_type
   *   The entity type identifier.
   * @param string $field_name
   *   The field name.
   * @param $delta
   *   The suggestion offset.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function selectAccess($entity_type, $field_name, $delta) {
    $config = $this->config('field_suggestion.settings');
    $field_names = (array) $config->get('fields');
    $entity_type = $this->helper->decode($entity_type);

    return AccessResult::allowedIf(
      !empty($field_names[$entity_type]) &&
      in_array($field_name, $field_names[$entity_type])
    );
  }

  /**
   * Pin or ignore values of selected fields.
   *
   * @param string $entity_type
   *   The entity type identifier.
   * @param int $entity_id
   *   The entity identifier.
   * @param string $field_name
   *   The field name.
   * @param string $type
   *   The action type.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  public function operation($entity_type, $entity_id, $field_name, $type) {
    $property = $this->property($entity_type, $field_name);

    $value = $this->entityTypeManager()->getStorage($entity_type)
      ->load($entity_id)
      ->$field_name
      ->$property;

    $storage = $this->entityTypeManager()->getStorage('field_suggestion');

    $entities = $storage->loadByProperties($values = [
      'type' => $field_type = $this->definition->getType(),
      'ignore' => $type === 'ignore',
      'entity_type' => $entity_type,
      'field_name' => $field_name,
      $this->helper->field($field_type) => $value,
    ]);

    if (!empty($entities)) {
      $storage->delete($entities);
    }
    else {
      $values['ignore'] = !$values['ignore'];
      $entities = $storage->loadByProperties($values);
      $values['ignore'] = !$values['ignore'];

      if (!empty($entities)) {
        /** @var \Drupal\field_suggestion\FieldSuggestionInterface $entity */
        foreach ($entities as $entity) {
          $entity->setIgnored($values['ignore'])->save();
        }
      }
      else {
        $storage->create($values)->save();
      }
    }

    $this->invalidator->invalidateTags(['field_suggestion_operations']);

    return $this->redirect('<front>');
  }

  /**
   * Access check based on whether a field is supported or not.
   *
   * @param string $entity_type
   *   The entity type identifier.
   * @param int $entity_id
   *   The entity identifier.
   * @param string $field_name
   *   The field name.
   * @param string $type
   *   The action type.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function operationAccess($entity_type, $entity_id, $field_name, $type) {
    if (
      !(
        isset(self::PERMISSIONS[$type]) &&
        (
          $this->currentUser()->hasPermission('administer field suggestion') ||
          $this->currentUser()->hasPermission(self::PERMISSIONS[$type])
        )
      ) ||
      !$this->selectAccess($entity_type, $field_name, 0)->isAllowed()
    ) {
      return AccessResult::neutral();
    }

    $entity_type = $this->helper->decode($entity_type);

    $entity = $this->entityTypeManager()->getStorage($entity_type)
      ->load($entity_id);

    if ($entity === NULL || ($field = $entity->$field_name)->isEmpty()) {
      return AccessResult::neutral();
    }

    $property = $this->property($entity_type, $field_name);

    $count = $this->entityTypeManager()->getStorage('field_suggestion')
      ->getQuery()
      ->accessCheck()
      ->condition('entity_type', $entity_type)
      ->condition('field_name', $field_name)
      ->condition(
        $this->helper->field($this->definition->getType()),
        $field->$property
      )
      ->range(0, 1)
      ->count()
      ->execute();

    return AccessResult::allowedIf(
      $count > 0 ||
      !in_array(
        $field->$property,
        $this->helper->ignored($entity_type, $field_name)
      )
    );
  }

  /**
   * Returns the name of the main property.
   *
   * @param $entity_type
   *   The entity type identifier.
   * @param $field_name
   *   The field name.
   *
   * @return string
   *   The name of the value property.
   */
  protected function property(&$entity_type, $field_name) {
    $entity_type = $this->helper->decode($entity_type);

    $this->definition = $this->entityFieldManager
      ->getBaseFieldDefinitions($entity_type)[$field_name];

    return $this->definition->getMainPropertyName() ?? 'value';
  }

}
