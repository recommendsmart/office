<?php

namespace Drupal\calendar_view\Plugin\views\pager;

use Drupal\calendar_view\Plugin\views\style\Calendar as CalendarStyle;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\pager\None as BasePager;

/**
 * The plugin to handle full pager.
 *
 * @ingroup views_pager_plugins
 *
 * @ViewsPager(
 *   id = "calendar",
 *   title = @Translation("Calendar navigation by month"),
 *   short_title = @Translation("Calendar"),
 *   help = @Translation("Create a navigation by month for your Calendar Views."),
 *   display_types = {"calendar"},
 *   theme = "calendar_view_pager"
 * )
 */
class Calendar extends BasePager {

  /**
   * {@inheritDoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['month_date_format'] = ['default' => 'F'];
    $options['display_reset'] = ['default' => TRUE];

    return $options;
  }

  /**
   * Provide the default form for setting options.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['offset']['#access'] = FALSE;

    $form['month_date_format'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Month label format'),
      '#description' => $this->t('Use any valid PHP date format.') . '<br>' .
      $this->t('Example: `F Y` for `December 2032` or `m` for `12`.'),
      '#default_value' => $this->options['month_date_format'] ?? 'F',
    ];

    $form['display_reset'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display "Back to today" reset button'),
      '#default_value' => $this->options['display_reset'] ?? TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function summaryTitle() {
    return $this->t('Navigation by month');
  }

  /**
   * Force pager display.
   */
  public function usePager() {
    return TRUE;
  }

  /**
   * Retrieve the calendar date from Calendar style plugin.
   *
   * @return string
   *   The timestamp (default: now).
   */
  protected function getCalendarTimestamp() {
    $style = $this->view->getStyle();
    if (!$style instanceof CalendarStyle) {
      return date('U');
    }

    return $style->getCalendarTimestamp();
  }

  /**
   * Perform any needed actions just before rendering.
   */
  public function preRender(&$result) {
    // Allow other plugins to use/alter timestamp.
    $this->view->calendar_timestamp = $this->getCalendarTimestamp();
  }

  /**
   * {@inheritdoc}
   */
  public function render($input) {
    $selected_timestamp = $this->view->calendar_timestamp;

    $now = new \DateTime();
    $now->setTimestamp($selected_timestamp);

    $last_month = clone $now;
    $last_month->modify('-1 month');

    $next_month = clone $now;
    $next_month->modify('+1 month');

    $input['previous'] = $last_month->getTimestamp();
    $input['current'] = $now->getTimestamp();
    $input['next'] = $next_month->getTimestamp();

    $date_formatter = \Drupal::service('date.formatter');
    $date_format = 'custom';
    $date_pattern = $this->options['month_date_format'] ?? 'F';

    return [
      '#theme' => $this->themeFunctions(),
      '#element' => Html::getUniqueId($this->getPluginId()),
      '#tags' => [
        0 => NULL,
        1 => $date_formatter->format($input['previous'], $date_format, $date_pattern),
        2 => $date_formatter->format($input['current'], $date_format, $date_pattern),
        3 => $date_formatter->format($input['next'], $date_format, $date_pattern),
      ],
      '#parameters' => $input + [
        'date_format' => $date_format,
        'date_pattern' => $date_pattern,
        'display_reset' => $this->option['display_reset'] ?? TRUE,
      ],
      '#view' => $this->view,
      '#route_name' => !empty($this->view->live_preview) ? '<current>' : '<none>',
    ];
  }

}
