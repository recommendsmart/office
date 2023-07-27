<?php

namespace Drupal\node_singles\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\Bundle;
use Drupal\views\ViewExecutable;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base class for filters that limit the bundles to a fixed list.
 */
abstract class LimitBundle extends Bundle {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, ?array &$options = NULL): void {
    parent::init($view, $display, $options);
    $this->value = $this->getBundles();
    $this->options['value'] = array_intersect_key(
          $this->getValueOptions(),
          array_flip($this->getBundles())
      );
    $this->options['expose']['reduce'] = 1;

    $this->definition['allow empty'] = FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposeForm(&$form, FormStateInterface $form_state) {
    parent::buildExposeForm($form, $form_state);
    // Reducing the allowed bundles is the whole point of this plugin.
    $form['expose']['reduce']['#disabled'] = TRUE;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    if ($this->isAGroup()) {
      return $this->t('grouped');
    }
    if (!empty($this->options['exposed'])) {
      return $this->t('exposed');
    }

    if ($this->operator !== 'in') {
      return $this->t('inverted');
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state): void {
    parent::valueForm($form, $form_state);

    // Disable the checkboxes on the config form.
    $formObject = $form_state->getFormObject();
    if ($formObject !== NULL && $formObject->getFormId() === 'views_ui_config_item_form') {
      $form['value']['#disabled'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function valueSubmit($form, FormStateInterface $form_state): void {
    // Don't actually store the selected values.
    $form_state->setValue(['options', 'value'], []);
  }

  /**
   * Return an array of bundles this filter should be limited to.
   *
   * @return string[]
   *   An array of bundles.
   */
  abstract protected function getBundles(): array;

}
