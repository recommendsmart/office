<?php

namespace Drupal\widget_type\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Interactive Components controller.
 */
class InteractiveComponents extends ControllerBase {

  /**
   * The menu link tree.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeElement
   */
  private $menuTree;

  /**
   * InteractiveComponents constructor.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   */
  public function __construct(MenuLinkTreeInterface $menu_link_tree) {
    $parameters = new MenuTreeParameters();
    $parameters->setRoot('interactive_components');
    $parameters->setMaxDepth(1);
    $this->menuTree = current($menu_link_tree->load('admin', $parameters));
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static($container->get('menu.link_tree'));
  }

  /**
   * Hides the video and remembers not to show it again.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The response.
   */
  public function hideVideo(): AjaxResponse {
    // Remember decision.
    $this->state()->set('widget_type.hide_video_thumbnail', TRUE);
    $response = new AjaxResponse();
    $response->addCommand(new RemoveCommand('#video-thumbnail'));
    return $response;
  }

  /**
   * Controller entry point.
   *
   * @return array
   *   The response render array.
   *
   * @SuppressWarnings(PHPMD.StaticAccess)
   */
  public function __invoke() {
    // Make sure to list all the items in the menu.
    $links = array_map(static function (MenuLinkTreeElement $element) {
      $menu_link = $element->link;
      return [
        'title' => $menu_link->getTitle(),
        'url' => $menu_link->getUrlObject(),
      ];
    }, $this->menuTree->subtree);
    $video_thumbnail = [
      '#type' => 'container',
      '#attributes' => ['id' => 'video-thumbnail'],
      [
        '#type' => 'link',
        '#url' => Url::fromUri('https://video.mateuaguilo.com/w/u6JoJivsYPmFnm5KYsjxD1'),
        '#title' => [
          '#theme' => 'image',
          '#uri' => \Drupal::service('extension.list.module')->getPath('widget_type') . '/assets/video-series.png',
          '#alt' => 'Image lazy load testing image',
          '#width' => '350',
        ],
      ],
      [
        '#type' => 'link',
        '#url' => Url::fromRoute('interactive_components.hide_video'),
        '#title' => $this->t('Hide this.'),
        '#attributes' => ['class' => ['use-ajax', 'hide-video']],
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
            'widget_type/widget_type.admin',
          ],
        ],
      ],
    ];
    return [
      'intro' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t(
          'Interactive components are widget instances that are placed in a page. You can create widget instances manually or you can create them directly in the page builder pages.'
        ),
      ],
      'docs' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t(
          'Watch the 📼 @link 📼 to gain more insight about the widget architecture.',
          [
            '@link' => Link::fromTextAndUrl(
              $this->t('introduction videos'),
              Url::fromUri('https://video.mateuaguilo.com/w/u6JoJivsYPmFnm5KYsjxD1'
              )
            )->toString(),
          ]
        ),
      ],
      'videos' => [
        $this->state()->get('widget_type.hide_video_thumbnail', FALSE) ? [] : $video_thumbnail,
      ],
      'types' => [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t(
          'Each widget instance is has a widget type, or widget definition. Widget definitions are created outside of Drupal by ingesting them from the widget registry using the %link module.',
          [
            '%link' => Link::fromTextAndUrl(
              $this->t('Widget Ingestion'),
              Url::fromUri('https://www.drupal.org/project/widget_ingestion')
            )->toString(),
          ]
        ),
      ],
      'links' => [
        '#theme' => 'links',
        '#links' => $links,
      ],
    ];
  }

}
