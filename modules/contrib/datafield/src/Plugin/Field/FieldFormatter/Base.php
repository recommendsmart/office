<?php

namespace Drupal\datafield\Plugin\Field\FieldFormatter;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\datafield\Plugin\Field\FieldType\DataField as DataFieldItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for data field formatters.
 */
abstract class Base extends FormatterBase {

  /**
   * File url generator object.
   *
   * @var \Drupal\Core\File\FileUrlGeneratorInterface
   */
  protected $fileUrlGenerator;

  /**
   * The storage handler class for files.
   *
   * @var \Drupal\file\FileStorage
   */
  private $fileStorage;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The entity display repository.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a FormatterBase object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity type manager service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, FileUrlGeneratorInterface $fileUrlGenerator, EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, EntityDisplayRepositoryInterface $entity_display_repository) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->fileUrlGenerator = $fileUrlGenerator;
    $this->entityTypeManager = $entity_type_manager;
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->dateFormatter = $date_formatter;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id, $plugin_definition, $configuration['field_definition'],
      $configuration['settings'], $configuration['label'],
      $configuration['view_mode'], $configuration['third_party_settings'],
      $container->get('file_url_generator'),
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('entity_display.repository')
    );
  }

  /**
   * Subfield types that can be rendered as a link.
   *
   * @var array
   */
  protected static $linkTypes = ['email', 'telephone', 'uri'];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    $default = [
      'formatter_settings' => [],
      'ajax' => FALSE,
      'custom_class' => '',
      'line_operations' => FALSE,
      'form_format_table' => FALSE,
    ];
    return $default + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $formatter_settings_default = [
      'hidden' => FALSE,
      'show_label' => FALSE,
      'link' => FALSE,
      'format_type' => 'medium',
      'thousand_separator' => '',
      'decimal_separator' => '.',
      'scale' => 2,
      'key' => FALSE,
      'view_mode' => 'default',
      'custom_date_format' => '',
      'plugin_type' => '',
      'weight' => 0,
      'image_style' => 'medium',
      'sum_column' => FALSE,
    ];
    $settings = $this->getSetting('formatter_settings');
    $field_settings = $this->getFieldSettings();
    $types = DataFieldItem::subfieldTypes();
    $subfields = $field_settings["columns"];
    if (!empty($settings) && count($subfields) == count($settings)) {
      uasort($settings, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = $settings;
    }
    $element = [
      'ajax' => [
        '#title' => $this->t('Load data with ajax'),
        '#description' => $this->t('Use ajax to load big data'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('ajax'),
      ],
      'custom_class' => [
        '#title' => $this->t('Set table class'),
        '#type' => 'textfield',
        '#default_value' => $this->getSetting('custom_class'),
      ],
      'line_operations' => [
        '#title' => $this->t('Show operations'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('line_operations'),
      ],
      'form_format_table' => [
        '#title' => $this->t('Format table in add / edit form'),
        '#type' => 'checkbox',
        '#default_value' => $this->getSetting('form_format_table'),
      ],
    ];
    // General settings.
    foreach (array_keys($subfields) as $subfield) {
      $item = $field_settings["columns"][$subfield];
      if (empty($settings[$subfield])) {
        $settings[$subfield] = $formatter_settings_default;
      }
      else {
        $settings[$subfield] += $formatter_settings_default;
      }
      $type = $item['type'];
      $title = $item['name'] . ' - ' . $types[$type];
      if (!empty($field_settings['field_settings']) && $field_settings['field_settings'][$subfield]['list']) {
        $title .= ' (' . $this->t('list') . ')';
      }

      $element['formatter_settings'][$subfield] = [
        '#title' => $title,
        '#type' => 'details',
      ];

      $element['formatter_settings'][$subfield]['link'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Display as link'),
        '#default_value' => $settings[$subfield]['link'],
        '#weight' => -10,
        '#access' => in_array($type, static::$linkTypes),
      ];

      if (in_array($type, ['datetime_iso8601', 'date'])) {
        $format_types = $this->entityTypeManager->getStorage('date_format')
          ->loadMultiple();
        $time = new DrupalDateTime();
        $options = [];
        foreach ($format_types as $type => $type_info) {
          $format = $this->dateFormatter->format($time->getTimestamp(), $type);
          $options[$type] = $type_info->label() . ' (' . $format . ')';
        }
        $options['custom'] = $this->t('Custom date format');
        $element['formatter_settings'][$subfield]['format_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Date format'),
          '#description' => $this->t('Choose a format for displaying the date.'),
          '#options' => $options,
          '#default_value' => $settings[$subfield]['format_type'],
        ];
        $element['formatter_settings'][$subfield]['custom_date_format'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Custom date format'),
          '#description' => $this->t('See <a href="https://www.php.net/manual/datetime.format.php#refsect1-datetime.format-parameters" target="_blank">the documentation for PHP date formats</a>.'),
          '#default_value' => $settings[$subfield]['custom_date_format'] ?: '',
          '#states' => [
            'visible' => [
              'select[name$="[' . $subfield . '][format_type]"]' => ['value' => 'custom'],
            ],
          ],
        ];
      }
      else {
        $element['formatter_settings'][$subfield]['format_type'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['format_type'],
        ];
      }

      $element['formatter_settings'][$subfield]['hidden'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Hidden'),
        '#default_value' => $settings[$subfield]['hidden'],
      ];

      $element['formatter_settings'][$subfield]['show_label'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Show label'),
        '#default_value' => $settings[$subfield]['show_label'],
      ];

      if (!empty($field_settings['field_settings']) && !empty($field_settings['field_settings'][$subfield]['list'])) {
        $element['formatter_settings'][$subfield]['key'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Display key instead of label'),
          // @todo Remove the fallback on 5.x.
          '#default_value' => $settings[$subfield]['key'] ?? FALSE,
        ];
      }

      if ($type == 'numeric' || $type == 'float' || $type == 'integer') {
        $options = [
          '' => $this->t('- None -'),
          '.' => $this->t('Decimal point'),
          ',' => $this->t('Comma'),
          ' ' => $this->t('Space'),
          chr(8201) => $this->t('Thin space'),
          "'" => $this->t('Apostrophe'),
        ];
        $element['formatter_settings'][$subfield]['thousand_separator'] = [
          '#type' => 'select',
          '#title' => $this->t('Thousand marker'),
          '#options' => $options,
          '#default_value' => $settings[$subfield]['thousand_separator'],
        ];
      }
      else {
        $element['formatter_settings'][$subfield]['thousand_separator'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['thousand_separator'],
        ];
      }

      if ($type == 'numeric' || $type == 'float') {
        $element['formatter_settings'][$subfield]['decimal_separator'] = [
          '#type' => 'select',
          '#title' => $this->t('Decimal marker'),
          '#options' => [
            '.' => $this->t('Decimal point'),
            ',' => $this->t('Comma'),
          ],
          '#default_value' => $settings[$subfield]['decimal_separator'],
        ];
        $element['formatter_settings'][$subfield]['scale'] = [
          '#type' => 'number',
          '#title' => $this->t('Scale', [], ['context' => 'decimal places']),
          '#min' => 0,
          '#max' => 10,
          '#default_value' => $settings[$subfield]['scale'],
          '#description' => $this->t('The number of digits to the right of the decimal.'),
        ];
      }
      else {
        $element['formatter_settings'][$subfield]['decimal_separator'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['decimal_separator'],
        ];
        $element['formatter_settings'][$subfield]['scale'] = [
          '#type' => 'value',
          '#default_value' => $settings[$subfield]['scale'],
        ];
      }
      if ($type == 'entity_reference') {
        $element['formatter_settings'][$subfield]['plugin_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Formatter'),
          '#options' => [
            'entity_reference_label' => $this->t('Entity label'),
            'entity_reference_entity_view' => $this->t('Rendered entity'),
            'entity_reference_entity_id' => $this->t('Entity ID'),
          ],
          '#default_value' => $settings[$subfield]['plugin_type'],
        ];
        $extract = explode(':', $field_settings["field_settings"][$subfield]["entity_reference_type"]);
        $options = $this->entityDisplayRepository->getViewModeOptions(end($extract));
        $element['formatter_settings'][$subfield]['view_mode'] = [
          '#type' => 'select',
          '#options' => $options,
          '#title' => $this->t('View mode'),
          '#description' => $this->t('Output entity in this view mode.'),
          '#default_value' => $settings[$subfield]['view_mode'],
          '#states' => [
            'visible' => [
              'select[name$="[' . $subfield . '][plugin_type]"]' => ['value' => 'entity_reference_entity_view'],
            ],
          ],
        ];
        $element['formatter_settings'][$subfield]['link'] = [
          '#title' => $this->t('Link label to the referenced entity'),
          '#type' => 'checkbox',
          '#default_value' => $settings[$subfield]['link'],
          '#states' => [
            'visible' => [
              'select[name$="[' . $subfield . '][plugin_type]"]' => ['value' => 'entity_reference_label'],
            ],
          ],
        ];
      }

      if ($type == 'file') {
        $element['formatter_settings'][$subfield]['plugin_type'] = [
          '#type' => 'select',
          '#title' => $this->t('Formatter'),
          '#options' => [
            'file_default' => $this->t("Generic file"),
            'file_table' => $this->t("Table of files"),
            'file_url_plain' => $this->t("URL to file"),
            'image' => $this->t("Image"),
            'image_url' => $this->t("URL to image"),
          ],
          "#empty_option" => $this->t('- Select -'),
          '#default_value' => $settings[$subfield]['plugin_type'],
        ];

        $image_styles = image_style_options(FALSE);
        $description_link = Link::fromTextAndUrl(
          $this->t('Configure Image Styles'),
          Url::fromRoute('entity.image_style.collection')
        );
        $element['formatter_settings'][$subfield]['image_style'] = [
          '#title' => $this->t('Image style'),
          '#type' => 'select',
          '#empty_option' => $this->t('None (original image)'),
          '#options' => $image_styles,
          '#description' => $description_link->toRenderable(),
          '#default_value' => $settings[$subfield]['image_style'],
          '#states' => [
            'visible' => [
              'select[name$="[' . $subfield . '][plugin_type]"]' => [
                ['value' => 'image'],
                ['value' => 'image_url'],
              ],
            ],
          ],
        ];
      }

      $element['formatter_settings'][$subfield]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for fields'),
        // '#title_display' => 'invisible',
        '#default_value' => $settings[$subfield]['weight'],
        '#weight' => 20,
      ];
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $settings = $this->getSetting('formatter_settings');
    $field_settings = $this->getFieldSettings();

    $subfield_types = DataFieldItem::subfieldTypes();

    $summary = [];
    foreach ($field_settings["columns"] as $subfield => $item) {
      $subfield_type = $item['type'];
      if (empty($settings[$subfield]) || empty($field_settings["field_settings"])) {
        continue;
      }
      $summary[] = new FormattableMarkup(
        '<b>@subfield - @subfield_type@list</b>',
        [
          '@subfield' => $field_settings["field_settings"][$subfield]["label"],
          '@subfield_type' => strtolower($subfield_types[$subfield_type]),
          '@list' => $field_settings["field_settings"][$subfield]["list"] ? ' (' . $this->t('list') . ')' : '',
        ]
      );
      if (isset($settings[$subfield]['format_type']) && $subfield_type == 'datetime_iso8601') {
        $summary[] = $this->t('Date format: @format', ['@format' => $settings[$subfield]['format_type']]);
      }
      if (isset($settings[$subfield]['link']) && in_array($subfield_type, static::$linkTypes)) {
        $summary[] = $this->t('Link: @value', ['@value' => $settings[$subfield]['link'] ? $this->t('yes') : $this->t('no')]);
      }
      if (isset($settings[$subfield]['hidden'])) {
        $summary[] = $this->t('Hidden: @value', ['@value' => $settings[$subfield]['hidden'] ? $this->t('yes') : $this->t('no')]);
      }
      if (!empty($field_settings[$subfield]["list"])) {
        // @todo Remove the fallback in 5.x.
        $display_key = $settings[$subfield]['key'] ?? FALSE;
        $summary[] = $this->t('Display key: @value', ['@value' => $display_key ? $this->t('yes') : $this->t('no')]);

      }
      if ($subfield_type == 'numeric' || $subfield_type == 'float' || $subfield_type == 'integer') {
        $summary[] = $this->t('Number format: @format', ['@format' => $this->numberFormat($subfield, 1234.1234567890)]);
      }
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL): array {
    $elements = [];
    if (count($items) > 0) {
      // A field may appear multiple times in a single view. Since items are
      // passed by reference we need to ensure they are processed only once.
      $items = clone $items;
      $this->prepareItems($items, $langcode);
      $elements = parent::view($items, $langcode);
    }
    return $elements;
  }

  /**
   * Prepare field items.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $items
   *   List of field items.
   * @param string $langcode
   *   Language code.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  protected function prepareItems(FieldItemListInterface $items, $langcode = NULL): void {

    $field_settings = $this->getFieldSettings();
    $settings = $this->getSettings();
    $subfields = $storage = $field_settings["columns"];
    if (!empty($setting = $settings['formatter_settings'])) {
      uasort($setting, [
        'Drupal\Component\Utility\SortArray',
        'sortByWeightElement',
      ]);
      $subfields = $setting;
    }
    foreach ($items as $delta => $item) {
      foreach (array_keys($subfields) as $subfield) {
        if (empty($storage[$subfield])) {
          continue;
        }
        $storage = $field_settings["columns"][$subfield];

        if (!empty($settings['formatter_settings']) && $settings['formatter_settings'][$subfield]['hidden']) {
          $item->{$subfield} = NULL;
        }
        else {

          $type = $storage['type'];

          if ($type == 'boolean') {
            $item->{$subfield} = $field_settings[$subfield][$item->{$subfield} ? 'on_label' : 'off_label'];
          }

          // Empty string should already be converted into NULL.
          // @see Drupal\datafield\Plugin\Field\FieldWidget\DataField::massageFormValues()
          if ($item->{$subfield} === NULL) {
            continue;
          }

          if ($type == 'numeric' || $type == 'float' || $type == 'integer') {
            $item->{$subfield} = $this->numberFormat($subfield, $item->{$subfield});
          }

          if (in_array($type, ['datetime_iso8601', 'date']) &&
            $item->{$subfield} &&
            in_array($storage["datetime_type"], [
              'datetime', 'timestamp', 'time', 'date', 'week', 'month',
            ])) {
            // We follow the same principles as Drupal Core.
            // In the case of a datetime subfield, the date must be parsed using
            // the storage time zone and converted to the user's time zone while
            // a date-only field should have no timezone conversion performed.
            $timezone = $storage['datetime_type'] === 'datetime' ?
              date_default_timezone_get() : DataFieldItem::DATETIME_STORAGE_TIMEZONE;
            if ($storage["datetime_type"] == 'timestamp' && is_numeric($item->{$subfield})) {
              $timestamp = $item->{$subfield};
            }
            else {
              $datetime = new DrupalDateTime($item->{$subfield}, 'UTC');
              $timestamp = $datetime->getTimestamp();
            }
            if ($type == 'datetime_iso8601') {
              if (in_array($storage["datetime_type"], ['datetime', 'date'])) {
                $timestamp = $items[$delta]->createDate($subfield)
                  ->getTimestamp();
              }
            }

            $formatType = $settings['formatter_settings'][$subfield]['format_type'] ?? 'short';
            $customFormat = '';
            if (!empty($settings['formatter_settings'][$subfield]['custom_date_format'])) {
              $customFormat = $settings['formatter_settings'][$subfield]['custom_date_format'];
            }

            $date = $this->dateFormatter->format($timestamp, $formatType, $customFormat, $timezone);
            if ($storage["datetime_type"] == 'timestamp' && is_numeric($item->{$subfield})) {
              $date = $this->dateFormatter->format($timestamp, $formatType);
            }
            if ($field_settings["columns"][$subfield]["datetime_type"] == 'time' && $type == 'date') {
              $date = $this->dateFormatter->format($timestamp, 'custom', 'H:i:s');
            }
            $item->{$subfield} = [
              '#theme' => 'time',
              '#langcode' => $langcode,
              '#text' => $date,
              '#html' => FALSE,
              '#attributes' => [
                'datetime' => $this->dateFormatter->format($timestamp, 'custom', 'Y-m-d\TH:i:s') . 'Z',
              ],
              '#cache' => ['contexts' => ['timezone']],
            ];
          }

          $original_value[$subfield] = $item->{$subfield};
          if (!empty($field_settings["field_settings"]) && $field_settings["field_settings"][$subfield]['list']) {
            // @todo Remove the fallback in 5.x.
            $display_key = $settings['formatter_settings'][$subfield]['key'] ?? FALSE;
            if (!$display_key) {
              // Replace the value with its label if possible.
              $item->{$subfield} = $field_settings["field_settings"][$subfield]['allowed_values'][$item->{$subfield}] ?? NULL;
            }
          }

          if (!empty($settings['formatter_settings']) && !empty($settings['formatter_settings'][$subfield]['link'])) {
            $value = $original_value[$subfield] ?: $item->{$subfield};
            switch ($type) {
              case 'email':
                $item->{$subfield} = [
                  '#type' => 'link',
                  '#langcode' => $langcode,
                  '#title' => $item->{$subfield},
                  '#url' => Url::fromUri('mailto:' . $value),
                ];
                break;

              case 'telephone':
                $item->{$subfield} = [
                  '#type' => 'link',
                  '#langcode' => $langcode,
                  '#title' => $item->{$subfield},
                  '#url' => Url::fromUri('tel:' . rawurlencode(preg_replace('/\s+/', '', $value))),
                  '#options' => ['external' => TRUE],
                ];
                break;

              case 'uri':
                $item->{$subfield} = [
                  '#type' => 'link',
                  '#langcode' => $langcode,
                  '#title' => $item->{$subfield},
                  '#url' => Url::fromUri($value),
                  '#options' => ['external' => TRUE],
                ];
                break;

            }
          }

          if ($type == 'entity_reference' && !empty($settings['formatter_settings'][$subfield]['plugin_type'])) {
            $explode = explode(':', $field_settings['field_settings'][$subfield]["entity_reference_type"]);
            $reference_type = end($explode);
            $entity_id = $item->{$subfield};
            $entity = $this->entityTypeManager->getStorage($reference_type)->load($entity_id);
            switch ($settings["formatter_settings"][$subfield]["plugin_type"]) {
              case 'entity_reference_entity_view':
                $view_mode = 'full';
                if (!empty($settings["formatter_settings"][$subfield]["view_mode"])) {
                  $view_mode = $settings["formatter_settings"][$subfield]["view_mode"];
                }
                $view_builder = $this->entityTypeManager->getViewBuilder($reference_type);
                $item->{$subfield} = $view_builder->view($entity, $view_mode);
                break;

              case 'entity_reference_label':
                if (!empty($settings["formatter_settings"][$subfield]["link"])) {
                  $item->{$subfield} = [
                    '#type' => 'link',
                    '#langcode' => $langcode,
                    '#title' => $entity->label(),
                    '#url' => $entity->toUrl(),
                    '#cache' => ['tags' => $entity->getCacheTags()],
                  ];
                }
                else {
                  $item->{$subfield} = [
                    '#plain_text' => $entity->label(),
                    '#langcode' => $langcode,
                    '#cache' => ['tags' => $entity->getCacheTags()],
                  ];
                }
                break;

              default:
                $item->{$subfield} = [
                  '#plain_text' => $entity->id(),
                  '#langcode' => $langcode,
                  '#cache' => [
                    'tags' => $entity->getCacheTags(),
                  ],
                ];
                break;
            }

          }

          if ($type == 'file' && !empty($fid = $item->{$subfield})) {
            $file = $this->fileStorage->load($fid);
            if (empty($file)) {
              $item->{$subfield} = NULL;
              continue;
            }
            $url = $this->fileUrlGenerator->generate($file->getFileUri());
            if (!empty($plugin_type = $settings['formatter_settings'][$subfield]['plugin_type'])) {
              switch ($plugin_type) {
                case 'file_default':
                  $item->{$subfield} = [
                    '#theme' => 'file_link',
                    '#file' => $file,
                    '#description' => !empty($item->description) ? $item->description : NULL,
                    '#cache' => ['tags' => $file->getCacheTags()],
                  ];
                  break;

                case 'file_table':
                  $header = [$this->t('Attachment'), $this->t('Size')];
                  $rows[] = [
                    [
                      'data' => [
                        '#theme' => 'file_link',
                        '#file' => $file,
                        '#cache' => ['tags' => $file->getCacheTags()],
                      ],
                    ],
                    ['data' => format_size($file->getSize())],
                  ];
                  $item->{$subfield} = [
                    '#theme' => 'table__file_formatter_table',
                    '#header' => $header,
                    '#rows' => $rows,
                  ];
                  break;

                case 'file_url_plain':
                  $item->{$subfield} = [
                    '#markup' => $file->createFileUrl(),
                    '#cache' => ['tags' => $file->getCacheTags()],
                  ];
                  break;

                case 'image':
                  if (empty($setting[$subfield]['image_style'])) {
                    $item->{$subfield} = [
                      '#theme' => 'image',
                      '#uri' => $file->getFileUri(),
                      "#attributes" => ['class' => ['img-fluid']],
                    ];
                  }
                  else {
                    $item->{$subfield} = [
                      '#theme' => 'image_style',
                      '#style_name' => $setting[$subfield]['image_style'],
                      '#uri' => $file->getFileUri(),
                      "#attributes" => [
                        'class' => ['img-fluid', 'img-thumbnail'],
                      ],
                      '#cache' => [
                        'tags' => $file->getCacheTags(),
                      ],
                    ];
                  }
                  break;

                case 'image_url':
                  $image_uri = $file->getFileUri();
                  $image_style = $this->entityTypeManager->getStorage('image_style')
                    ->load($setting[$subfield]['image_style']);
                  $url = $image_style ? $this->fileUrlGenerator->transformRelative($image_style->buildUrl($image_uri)) : $this->fileUrlGenerator->generateString($image_uri);

                  $item->{$subfield} = [
                    '#markup' => $url,
                    '#cache' => ['tags' => $file->getCacheTags()],
                  ];
                  // @todo add Cache to image style.
                  break;
              }
            }
            elseif (!empty($url)) {
              $fieldName = $file->getFilename();
              $ext = pathinfo($fieldName, PATHINFO_EXTENSION);
              $title = new FormattableMarkup('<i class="bi bi-filetype-@ext"></i> ' . $fieldName, [
                '@ext' => strtolower($ext),
              ]);
              $item->{$subfield} = [
                '#type' => 'link',
                '#langcode' => $langcode,
                '#title' => $title,
                '#url' => $url,
                '#cache' => ['tags' => $file->getCacheTags()],
              ];
            }

          }

          if ($type == 'json') {
            $fieldName = $item->getFieldDefinition()->getName() . '-' . $subfield;
            $settings = ['collapse' => TRUE];
            $item->{$subfield} = [
              '#type' => 'html_tag',
              '#tag' => 'pre',
              '#value' => $item->{$subfield},
              '#langcode' => $langcode,
              '#attributes' => [
                'data-json-field' => $fieldName,
                'class' => ['json-view'],
              ],
              '#attached' => [
                'library' => ['datafield/jquery_jsonview'],
                'drupalSettings' => [
                  'json_view' => [$fieldName => $settings],
                ],
              ],
            ];
          }
          if ($type == 'text') {
            $item->{$subfield} = [
              '#type' => 'inline_template',
              '#template' => $item->{$subfield},
            ];
          }
        }

      }
      $items[$delta] = $item;
    }
  }

  /**
   * Formats a number.
   */
  protected function numberFormat(string $subfield, string $number): string {
    $settings = $this->getSetting('formatter_settings')[$subfield];
    if ($this->getFieldSetting('columns')[$subfield]['type'] == 'integer') {
      $settings['scale'] = 0;
    }
    return number_format($number, $settings['scale'], $settings['decimal_separator'], $settings['thousand_separator']);
  }

  /**
   * Check permission Operation.
   */
  public static function checkPermissionOperation($entity, $fieldName) {
    $hasPermission = FALSE;
    $user = \Drupal::currentUser();
    $userRoles = \Drupal::currentUser()->getRoles();
    if (in_array('administrator', $userRoles)) {
      return $hasPermission = TRUE;
    }
    $permissions = [
      'bypass node access',
      'administer nodes',
      'create ' . $fieldName,
      'edit ' . $fieldName,
      'edit own ' . $fieldName,
    ];
    foreach ($permissions as $permission) {
      if ($user->hasPermission($permission)) {
        $hasPermission = TRUE;
        break;
      }
    }
    $entityType = $entity->getEntityTypeId();
    if (!$hasPermission && $entityType != 'user') {
      $uid = $entity->getOwnerId();
      if ($user->hasPermission($permission) && $uid && $uid == $user->id()) {
        $hasPermission = TRUE;
      }
    }
    return $hasPermission;
  }

}
