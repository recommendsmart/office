<?php

namespace Drupal\widget_type\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TypedData\Exception\MissingDataException;
use Drupal\file\FileInterface;
use Drupal\widget_type\WidgetRegistrySourceInterface;
use Drupal\widget_type\WidgetTypeInterface;

/**
 * Defines the widget type entity class.
 *
 * @ContentEntityType(
 *   id = "widget_type",
 *   label = @Translation("Widget Type"),
 *   label_collection = @Translation("Widget Types"),
 *   handlers = {
 *     "view_builder" = "Drupal\widget_type\WidgetTypeViewBuilder",
 *     "list_builder" = "Drupal\widget_type\WidgetTypeListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\widget_type\WidgetTypeAccessControlHandler",
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "widget_type",
 *   admin_permission = "administer widget_type entities",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "canonical" = "/admin/content/interactive-components/widget-type/{widget_type}",
 *     "delete-form" = "/admin/content/interactive-components/widget-type/{widget_type}/delete",
 *     "collection" = "/admin/content/interactive-components/widget-type",
 *   },
 * )
 */
final class WidgetType extends ContentEntityBase implements WidgetTypeInterface {

  use EntityChangedTrait;
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function label() {
    $parts = [];
    preg_match(
      '@(v[0-9]+)\.(.*)@',
      $this->getVersion(),
      $parts
    );
    $major_version = $parts[1] ?? 'v1';
    $source = $this->get('widget_registry_source');
    return $this->t('@name (@version | @source) ', [
      '@name' => parent::label(),
      '@version' => $major_version,
      '@source' => $source->entity instanceof WidgetRegistrySourceInterface ? $source->entity->label() : '',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return $this->get('name')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setName($title) {
    $this->set('name', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewLink(): string {
    return $this->get('preview_link')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setPreviewLink(string $link): self {
    $this->set('preview_link', $link);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPreviewImage(): array {
    $value = $this->get('preview_image');
    return $value ? [
      'file' => $value->entity,
      'alt' => $value->alt,
      'title' => $value->title,
    ] : [];
  }

  /**
   * {@inheritdoc}
   */
  public function setPreviewImage(FileInterface $file, string $alt, string $title): self {
    $this->set('preview_image', [
      'target_id' => $file->id(),
      'alt' => $alt,
      'title' => $title,
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled() {
    return (bool) $this->get('status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setStatus($status) {
    $this->set('status', $status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->get('description')->value ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function setDescription(string $description): WidgetTypeInterface {
    $this->set('description', $description);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteId(): string {
    return $this->get('remote_widget_id')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteId(string $remote_id): WidgetTypeInterface {
    $this->set('remote_widget_id', $remote_id);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): string {
    return $this->get('remote_widget_version')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setVersion(string $version): WidgetTypeInterface {
    $this->set('remote_widget_version', $version);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDirectory(): string {
    return $this->get('remote_widget_directory')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setDirectory(string $directory): WidgetTypeInterface {
    $this->set('remote_widget_directory', $directory);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSettingsSchema(): array {
    try {
      $first = $this->get('remote_widget_settings')->first();
      if (!$first) {
        return [];
      }
      return $first->getValue();
    } catch (MissingDataException $exception) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getFormSchema(): array {
    try {
      $first = $this->get('remote_widget_form_schema')->first();
      if (!$first) {
        return [];
      }
      return $first->getValue();
    } catch (MissingDataException $exception) {
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setSettingsSchema(array $settings): WidgetTypeInterface {
    $this->set('remote_widget_settings', $settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setFormSchema(array $settings): WidgetTypeInterface {
    $this->set('remote_widget_form_schema', $settings);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getFiles(): array {
    return array_map(
      static function ($file) {
        return $file['value'];
      },
      $this->get('remote_widget_files')->getValue()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setFiles(array $files): WidgetTypeInterface {
    $this->set('remote_widget_files', $files);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraryDependencies(): array {
    return array_map(
      static function ($file) {
        return $file['value'];
      },
      $this->get('remote_widget_dependencies')->getValue()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setLibraryDependencies(array $dependencies): WidgetTypeInterface {
    $this->set('remote_widget_dependencies', $dependencies);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteLanguages(): array {
    return array_map(
      static function ($lang) {
        return $lang['value'];
      },
      $this->get('available_translation_languages')->getValue()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteLanguages(array $lang_codes): WidgetTypeInterface {
    $this->set('available_translation_languages', $lang_codes);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRemoteStatus(): string {
    return $this->get('remote_widget_status')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setRemoteStatus($remote_status): WidgetTypeInterface {
    $this->set('remote_widget_status', $remote_status);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name of the widget type entity.'))
      ->setRequired(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDescription(t('A boolean indicating whether the widget type is enabled.'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => ['display_label' => FALSE],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of the widget type.'))
      ->setDisplayOptions('form', ['type' => 'text_textarea', 'weight' => 10])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the widget type was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the widget type was last edited.'));

    $fields += static::customBaseFieldDefinitions();
    return $fields;
  }

  /**
   * The field definitions to integrate with the widget registry.
   *
   * @return \Drupal\Core\Field\BaseFieldDefinition[]
   *   The definitions.
   */
  public static function customBaseFieldDefinitions(): array {
    $fields = [];
    $fields['remote_widget_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote Widget ID'))
      ->setDescription(t('The remote ID of the widget this refers to.'))
      ->setSettings(['max_length' => 128, 'text_processing' => 0])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->addConstraint('UniqueField')
      ->setReadOnly(TRUE);

    $fields['remote_widget_version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote Widget Version'))
      ->setDescription(t('The version of the widget last downloaded.'))
      ->setSettings(['max_length' => 16, 'text_processing' => 0])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -9,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setReadOnly(TRUE);

    $fields['remote_widget_directory'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote Widget Directory'))
      ->setDescription(t('The remote directory where the widget files live.'))
      ->setSettings(['max_length' => 255, 'text_processing' => 0])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -8,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setReadOnly(TRUE);

    $fields['remote_widget_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Remote Widget status'))
      ->setDescription(t('The list of files of the widget.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -2,
      ])
      ->setDefaultValue(WidgetTypeInterface::REMOTE_STATUS_UNKNOWN)
      ->setDisplayConfigurable('view', FALSE)
      ->setReadOnly(TRUE);

    $fields['remote_widget_settings'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Remote Widget Settings'))
      ->setDescription(t('The key/value settings from the widget server.'))
      ->setDefaultValue([]);

    $fields['remote_widget_form_schema'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Remote Widget Form Schema'))
      ->setDescription(t('The key/value form schema from the widget server.'))
      ->setDefaultValue([]);

    $fields['remote_widget_files'] = BaseFieldDefinition::create('string')
      ->setName('remote_widget_files')
      ->setLabel(t('Remote Widget Files'))
      ->setDescription(t('The list of files of the widget.'))
      ->setDefaultValue(NULL)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'string',
        'weight' => -2,
      ])
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE)
      ->setReadOnly(TRUE);

    $fields['remote_widget_dependencies'] = BaseFieldDefinition::create('map')
      ->setName('remote_widget_dependencies')
      ->setLabel(t('Remote Widget Dependencies'))
      ->setDescription(t('The list of dependencies of the widget.'))
      ->setDefaultValue([])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE)
      ->setReadOnly(TRUE);

    $fields['available_translation_languages'] = BaseFieldDefinition::create('string')
      ->setName('available_translation_languages')
      ->setLabel(t('Available translation languages'))
      ->setDescription(t('A list of language codes that the widgets of this type are available in.'))
      ->setDefaultValue(NULL)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setTranslatable(FALSE)
      ->setReadOnly(TRUE);

    $fields['widget_registry_source'] = BaseFieldDefinition::create('entity_reference')
      ->setRevisionable(TRUE)
      ->setLabel(t('Widget Registry Source'))
      ->setDescription(t('The widget registry source this widget type originated from.'))
      ->setSetting('target_type', 'widget_registry_source')
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['preview_link'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('Preview Link'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDescription(t('The link of the preview.'))
      ->setDisplayOptions('view', ['region' => 'hidden'])
      ->setDisplayConfigurable('view', TRUE);

    $fields['preview_image'] = BaseFieldDefinition::create('image')
      ->setLabel(t('Preview Image'))
      ->setDescription(t('The thumbnail of the widget type.'))
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayOptions('view', [
        'type' => 'image',
        'weight' => 5,
        'label' => 'hidden',
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getJsRenderFunctionName(): string {
    $camel_name = ucwords($this->getRemoteId(), '-');
    return sprintf('render%s', str_replace('-', '', $camel_name));
  }

  /**
   * {@inheritdoc}
   */
  public function negotiateLanguage($lang_code = NULL, $_parents = []): ?string {
    $language_manager = $this->languageManager();
    $lang_code = $lang_code ?: $language_manager
      ->getCurrentLanguage(LanguageInterface::TYPE_CONTENT)->getId();
    // If the language code is in the list of languages supported by the widget,
    // then return it.
    if (in_array($lang_code, $this->getRemoteLanguages(), TRUE)) {
      return $lang_code;
    }
    $candidates = $language_manager->getFallbackCandidates([
      'langcode' => $lang_code,
      'operation' => 'entity_view',
      'data' => $this,
    ]);
    foreach ($candidates as $candidate) {
      if (in_array($candidate, $this->getRemoteLanguages(), TRUE)) {
        return $candidate;
      }
    }
    return $language_manager->getDefaultLanguage()->getId();
  }

  /**
   * {@inheritdoc}
   */
  public function getJsSrc(): string {
    return sprintf('%s/js/main.js', $this->getDirectory());
  }

  /**
   * {@inheritdoc}
   */
  public function buildLibraryInfo(): array {
    $all_files = $this->getFiles();
    $check_by_extension = static function (string $extension) {
      return static function (string $file) use ($extension): bool {
        return (bool) preg_match('/\.' . $extension . '(\?.*)?$/', $file);
      };
    };
    $js_files = array_filter($all_files, $check_by_extension('js'));

    $js = array_reduce($js_files, static function (array $carry, string $file): array {
      $carry[$file] = [
        'external' => TRUE,
        'attributes' => ['defer' => TRUE],
      ];
      return $carry;
    }, []);
    // The JavaScript source file is always expected.
    $library = ['js' => $js];

    // Add CSS file if the widget requires it.
    if ($this->requiresCss()) {
      $css_files = array_filter($all_files, $check_by_extension('css'));
      $css = array_reduce($css_files, static function (array $carry, string $file): array {
        $carry[$file] = ['external' => TRUE];
        return $carry;
      }, []);
      $library['css'] = ['component' => $css];
    }
    // Add potential dependencies.
    $lib_deps = $this->getLibraryDependencies();
    if (empty($lib_deps)) {
      return $library;
    }
    $build_library_name = static function (array $lib_dep): ?string {
      if (empty($lib_dep['id'])) {
        return NULL;
      }
      $id = $lib_dep['id'];
      return sprintf('widget_type/widget_type.dep.%s', $id);
    };
    $library['dependencies'] = array_filter(
      array_map($build_library_name, $lib_deps)
    );
    return $library;
  }

  /**
   * {@inheritdoc}
   */
  public function requiresCss(): bool {
    return array_reduce($this->getFiles(), function (bool $matched, string $file) {
      return $matched || preg_match('/css\/main(\.[a-z0-9]*)?\.css$/', $file);
    }, FALSE);
  }

}
