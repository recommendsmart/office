<?php

namespace Drupal\maestro_form_approval_example;

use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\Extension\ModuleUninstallValidatorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Prevents example task module from being uninstalled when the task is bound in a template.
 */
class MaestroFormApprovalExampleUninstallValidator implements ModuleUninstallValidatorInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new MaestroFormApprovalExampleUninstallValidator.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation service.
   */
  public function __construct(TranslationInterface $string_translation) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * {@inheritdoc}
   */
  public function validate($module) {
    $reasons = [];
    if ($module == 'maestro_form_approval_example') {
      // Search for any node content of type "approval_form".
      $query = \Drupal::entityQuery('node')
        ->accessCheck(FALSE)
        ->condition('type', 'approval_form');

      $nids = $query->execute();
      if ($nids) {
        $reasons[] = $this->t('Uninstalling the module will orphan the Approval Form content.');
        $url = Url::fromRoute('system.admin_content', ['status' => 'All', 'type' => 'approval_form']);
        $link = Link::fromTextAndUrl($this->t('Click here to remove Approval Form content.'), $url);
        $reasons[] = $link;
      }
      // Now detect if this task still has open tasks...
      $query = \Drupal::entityQuery('maestro_process')
        ->accessCheck(FALSE)
        ->condition('template_id', 'form_approval_flow')
        ->condition('complete', '0');
      $pids = $query->execute();
      if ($pids) {
        $reasons[] = $this->t('There are active Form Approval Flow processes.  Complete or delete the open processes before uninstalling.');
      }

    }

    return $reasons;
  }

}
