<?php

namespace Drupal\go_back\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;

/**
 * Provides a Go Back Block.
 *
 * @Block(
 *   id = "go_back",
 *   admin_label = @Translation("Go Back Block"),
 *   category = @Translation("Go Back Block"),
 * )
 */
class GoBack extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;
  /**
   * The language service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageInt;
  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('language_manager'),
      $container->get('entity_type.manager')

    );
  }

  /**
   * This function construct a block.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The The route match service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageInt
   *   The The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $routeMatch, LanguageManagerInterface $languageInt, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->routeMatch = $routeMatch;
    $this->languageInt = $languageInt;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);
    $config = $this->getConfiguration();
    $form['active_description'] = [
      '#type' => 'item',
      '#title' => $this->t("How to use Active button."),
      '#description' => $this->t("Allows to show the block in the content type."),
    ];
    $form['smart_description'] = [
      '#type' => 'item',
      '#title' => $this->t("How to use Mode smart."),
      '#description' => $this->t("It allows us to add a custom url to the block button so that the user can go where we want, the customization is independent for each type of content. This url will be used also if the user comes from outside the site and the smart mode is activated."),
    ];
    $form['url_description'] = [
      '#type' => 'item',
      '#title' => $this->t("How to use URL Back (Custom URL)."),
      '#description' => $this->t("The button of the block will take as url the last one of the site that we have visited, we can activate this option for each type of content, in the case that we come from outside the site, the url custom will be url."),
    ];
    foreach ($this->getQuickDisplays() as $content_type) {
      $form[$content_type] = [
        '#type' => 'fieldset',
        '#title' => $content_type,
      ];
      $form[$content_type]['quick_display'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Active button in %content_type", [
          '%content_type' => $content_type,
        ]),
        '#options' => $content_type,
        '#default_value' => $config[$content_type]['quick_display'],
        '#attributes' => [
          'placeholder' => $this->t("Active button in %content_type", [
            '%content_type' => $content_type,
          ]),
        ],
      ];
      $form[$content_type]['smart_mode'] = [
        '#type' => 'checkbox',
        '#title' => $this->t("Active smart mode in %content_type", [
          '%content_type' => $content_type,
        ]),
        '#default_value' => $config[$content_type]['smart_mode'],
        '#states' => [
          'visible' => [
            ':input[name$="[' . $content_type . '][quick_display]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
      $form[$content_type]['go_back'] = [
        '#type' => 'textfield',
        '#title' => $this->t("URL Back - %content_type", [
          '%content_type' => $content_type,
        ]),
        '#default_value' => $config[$content_type]['go_back'],
        '#states' => [
          'visible' => [
            ':input[name$="[' . $content_type . '][quick_display]"]' => [
              'checked' => TRUE,
            ],
          ],
          'required' => [
            ':input[name$="[' . $content_type . '][quick_display]"]' => [
              'checked' => TRUE,
            ],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    foreach ($this->getQuickDisplays() as $content_type) {
      $values = $form_state->getValues();
      $this->configuration[$content_type]['go_back'] = $values[$content_type]['go_back'];
      $this->configuration[$content_type]['quick_display'] = $values[$content_type]['quick_display'];
      $this->configuration[$content_type]['smart_mode'] = $values[$content_type]['smart_mode'];

    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->getConfiguration();
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      /*
      $bundle: Get a content type name.
      $langcode: Get the language currently used.
       */
      $bundle = $node->type->entity->label();
      $langcode = $this->languageInt->getCurrentLanguage()->getId();
      // We activate go back block in a content type.
      if ($config[$bundle]['quick_display'] == '1') {
        // We activate smart mode in a content type.
        if ($config[$bundle]['smart_mode'] == '1') {
          // Take the URL of our site where it comes from.
          $urlb = $_SERVER['HTTP_REFERER'];
          if (strcmp($urlb, '') === 0) {
            // If the url is not from our site, take the url by default.
            if (substr($config[$bundle]['go_back'], 0, 1) == '/') {
              /*
              Transforms the url into a readable URL for Drupal
              and add the interface language.
               */
              $url = Url::fromUri('base:' . $config[$bundle]['go_back'], [
                'language' => $this->languageInt->getLanguage($langcode),
              ]);
              $path = $url->toString();
              $urlb = $path;
            }
          }
        }
        // When smart mode is not activated.
        else {
          /*
          Transforms the url into a readable URL for Drupal
          and add the interface language.
           */
          if (substr($config[$bundle]['go_back'], 0, 1) == '/') {
            $url = Url::fromUri('base:' . $config[$bundle]['go_back'], [
              'language' => $this->languageInt->getLanguage($langcode),
            ]);
            $path = $url->toString();
            $urlb = $path;
          }
        }
      }
      else {
        /*
        In case there is any error the
        block arrow will return #.
         */
        $urlb = '#';
      }
      return [
        '#theme' => 'block__goback',
        '#link' => $urlb,
        '#attached' => [
          'library' => [
            'go_back/go_back',
          ],
        ],
      ];

    }
  }

  /**
   * Show all display modes of content.
   */
  protected function getQuickDisplays() {
    // Show content types list.
    $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $contentTypesList = [];
    foreach ($contentTypes as $contentType) {
      $contentTypesList[$contentType->id()] = $contentType->label();
    }
    return $contentTypesList;

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    if ($node = $this->routeMatch->getParameter('node')) {
      return Cache::mergeTags(parent::getCacheTags(), ['node:' . $node->id()]);
    }
    else {
      return parent::getCacheTags();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return 0;
  }

}