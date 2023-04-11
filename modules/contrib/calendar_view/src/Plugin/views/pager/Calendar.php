<?php

namespace Drupal\calendar_view\Plugin\views\pager;

use Drupal\views\Plugin\views\pager\None as BasePager;

/**
 * The plugin to handle full pager.
 *
 * @ingroup views_pager_plugins
 *
 * @ViewsPager(
 *   id = "calendar",
 *   title = @Translation("Calendar pager (deprecated)"),
 *   short_title = @Translation("Calendar"),
 *   help = @Translation("Deprecated. Will be remove on next major release."),
 *   display_types = {"calendar"},
 *   theme = "calendar_view_pager"
 * )
 */
class Calendar extends BasePager {
}
