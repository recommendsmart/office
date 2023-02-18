<?php

namespace Drupal\widget_type;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\file\FileInterface;

/**
 * Provides an interface defining a widget type entity type.
 */
interface WidgetTypeInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * REMOTE_STATUS_<status> const denotes the status of the widget and its
   * readiness for production. It's a map of the js-widgets registry schema.
   *
   * @see https://js-widgets.github.io/js-widgets/registry-schema/#_items_status
   */
  /**
   * Widget status is stable.
   */
  const REMOTE_STATUS_STABLE = 'stable';

  /**
   * Widget status is beta.
   */
  const REMOTE_STATUS_BETA = 'beta';

  /**
   * Widget status is work in progress (WIP).
   */
  const REMOTE_STATUS_WIP = 'wip';

  /**
   * Widget status is deprecated.
   */
  const REMOTE_STATUS_DEPRECATED = 'deprecated';

  /**
   * Widget status is unknown. There is no way to establish the status. It's not
   * defined in the registry.
   */
  const REMOTE_STATUS_UNKNOWN = 'unknown';

  /**
   * Gets the widget type name.
   *
   * @return string
   *   Name of the widget type.
   */
  public function getName();

  /**
   * Sets the widget type name.
   *
   * @param string $name
   *   The widget type name.
   *
   * @return \Drupal\widget_type\WidgetTypeInterface
   *   The called widget type entity.
   */
  public function setName($name);

  /**
   * Gets the widget type creation timestamp.
   *
   * @return int
   *   Creation timestamp of the widget type.
   */
  public function getCreatedTime();

  /**
   * Sets the widget type creation timestamp.
   *
   * @param int $timestamp
   *   The widget type creation timestamp.
   *
   * @return \Drupal\widget_type\WidgetTypeInterface
   *   The called widget type entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Returns the widget type status.
   *
   * @return bool
   *   TRUE if the widget type is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets the widget type status.
   *
   * @param bool $status
   *   TRUE to enable this widget type, FALSE to disable.
   *
   * @return \Drupal\widget_type\WidgetTypeInterface
   *   The called widget type entity.
   */
  public function setStatus($status);

  /**
   * Set the widget type settings schema.
   *
   * @param array $settings
   *   The widget type version.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setSettingsSchema(array $settings): self;

  /**
   * Get the widget type description.
   *
   * @return string
   *   The widget type description.
   */
  public function getDescription(): string;

  /**
   * Set the widget type version.
   *
   * @param string $version
   *   The widget type version.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setVersion(string $version): self;

  /**
   * Set the widget type remote languages.
   *
   * @param array $lang_codes
   *   The widget type remote languages.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setRemoteLanguages(array $lang_codes): self;

  /**
   * @param $remote_status
   *
   * @return $this
   */
  public function setRemoteStatus($remote_status): self;

  /**
   * Get the remote widget type status.
   *
   * @return string
   *   The widget type description.
   */
  public function getRemoteStatus(): string;

  /**
   * Get the widget type settings.
   *
   * @return array
   *   The widget type settings schema.
   */
  public function getSettingsSchema(): array;

  /**
   * Get the widget files listing.
   *
   * @return string[]
   *   List of files of the widget.
   */
  public function getFiles(): array;

  /**
   * Set the widget files listing.
   *
   * @param array $files
   *   The list of widget files.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setFiles(array $files): self;

  /**
   * Set the widget type directory.
   *
   * @param string $directory
   *   The widget type version.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setDirectory(string $directory): self;

  /**
   * Get the widget type directory.
   *
   * @return string
   *   The widget type directory.
   */
  public function getDirectory(): string;

  /**
   * Get the widget type remote ID.
   *
   * @return string
   *   The widget type remote ID.
   */
  public function getRemoteId(): string;

  /**
   * Get the widget type remote languages.
   *
   * @return array
   *   The widget type remote languages.
   */
  public function getRemoteLanguages(): array;

  /**
   * Set the widget type description.
   *
   * @param string $description
   *   The widget type description.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setDescription(string $description): self;

  /**
   * Set the widget type remote ID.
   *
   * @param string $remote_id
   *   The widget type remote ID.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setRemoteId(string $remote_id): self;

  /**
   * Get the widget type version.
   *
   * @return string
   *   The widget type version.
   */
  public function getVersion(): string;

  /**
   * Gets the JS render function.
   *
   * @return string
   *   The name of the render function in the JS file.
   */
  public function getJsRenderFunctionName(): string;

  /**
   * Negotiates the language based on the Drupal language and the widget.
   *
   * @param string $lang_code
   *   The language code to negotiate. Defaults to the current content language.
   *
   * @return string|null
   *   The language to provide to the widget.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  public function negotiateLanguage($lang_code = NULL): ?string;

  /**
   * Get the JS source URL for the widget from the registry.
   *
   * @return string
   *   The JS file to import.
   */
  public function getJsSrc(): string;

  /**
   * Determines if the widget type has a CSS file on the registry.
   *
   * @return bool
   *   TRUE if the widget requires a CSS file, FALSE either.
   */
  public function requiresCss(): bool;

  /**
   * Builds the library definition as expected by the Library API.
   *
   * @return array
   *   An structured array compatible with hook_library_info_build.
   *
   * @see hook_library_info_build()
   */
  public function buildLibraryInfo(): array;

  /**
   * Gets the library dependencies.
   *
   * @return array
   *   The dependencies.
   */
  public function getLibraryDependencies(): array;

  /**
   * Sets the library dependencies.
   *
   * @param array $dependencies
   *   The dependencies.
   *
   * @return \Drupal\widget_type\WidgetTypeInterface
   *   The widget type for a fluent interface.
   */
  public function setLibraryDependencies(array $dependencies): WidgetTypeInterface;

  /**
   * Set the widget type form schema.
   *
   * @param array $settings
   *   The widget type form schema.
   *
   * @return \Drupal\widget_type\Entity\WidgetType
   *   The widget type.
   */
  public function setFormSchema(array $settings): WidgetTypeInterface;

  /**
   * Get the widget type form schema.
   *
   * @return array
   *   The widget type form schema.
   */
  public function getFormSchema(): array;

  /**
   * Gets the preview link.
   *
   * @return string
   *   The link.
   */
  public function getPreviewLink(): string;

  /**
   * Sets the preview image.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file.
   * @param string $alt
   *   The string.
   * @param string $title
   *   The title.
   *
   * @return $this
   *   The widget type.
   */
  public function setPreviewImage(FileInterface $file, string $alt, string $title): self;

  /**
   * Sets the preview link.
   *
   * @param string $link
   *   The preview link
   *
   * @return $this
   *   The widget type.
   */
  public function setPreviewLink(string $link): self;

  /**
   * Gets the preview image.
   *
   * @return array
   *   The preview image properties.
   */
  public function getPreviewImage(): array;

}
