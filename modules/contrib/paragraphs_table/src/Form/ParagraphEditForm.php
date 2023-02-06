<?php

namespace Drupal\paragraphs_table\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Paragraph Edit Form class.
 */
class ParagraphEditForm extends ContentEntityForm {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The content translation manager.
   *
   * @var \Drupal\content_translation\ContentTranslationManagerInterface
   */
  protected $manager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a BookOutlineForm object.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface|null $entity_type_bundle_info
   *   The entity type bundle service.
   * @param \Drupal\Component\Datetime\TimeInterface|null $time
   *   The time service.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(EntityRepositoryInterface $entity_repository, EntityTypeBundleInfoInterface $entity_type_bundle_info, TimeInterface $time, EntityFieldManagerInterface $entity_field_manager, LanguageManagerInterface $language_manager) {
    parent::__construct($entity_repository, $entity_type_bundle_info, $time);
    $this->entityFieldManager = $entity_field_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.repository'),
      $container->get('entity_type.bundle.info'),
      $container->get('datetime.time'),
      $container->get('entity_field.manager'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function init(FormStateInterface $form_state) {
    $langcode = $this->languageManager->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)
      ->getId();
    $form_state->set('langcode', $langcode);

    if (!$this->entity->hasTranslation($langcode)) {
      $translation_source = $this->entity;
      // @todo use dependency injection.
      $translation_manager = \Drupal::service('content_translation.manager');
      $host = $this->entity->getParentEntity();
      $host_source_langcode = $host->language()->getId();
      if ($host->hasTranslation($langcode)) {
        $host = $host->getTranslation($langcode);
        $host_source_langcode = $translation_manager->getTranslationMetadata($host)
          ->getSource();
      }

      if ($this->entity->hasTranslation($host_source_langcode)) {
        $translation_source = $this->entity->getTranslation($host_source_langcode);
      }

      $this->entity = $this->entity->addTranslation($langcode, $translation_source->toArray());
      $translation_manager->getTranslationMetadata($this->entity)->setSource($translation_source->language()->getId());
    }
    parent::init($form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $field_name = $this->entity->get('parent_field_name')->value;
    $host = $this->entity->getParentEntity();
    $entity_type = $host->getEntityTypeId();
    $bundle = $host->bundle();
    $entityFieldManager = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    $form['#title'] = $this->t('Edit %type item %id', [
      '%type' => $entityFieldManager[$field_name]->getLabel(),
      '%id' => $this->entity->id(),
    ]);
    $form = parent::form($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function save(array $form, FormStateInterface $form_state) {
    return $this->entity->save();
  }

}
