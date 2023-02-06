<?php

namespace Drupal\access_records\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for access record type deletion.
 *
 * @internal
 */
class AccessRecordTypeDeleteConfirm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $num = $this->entityTypeManager->getStorage('access_record')->getQuery()
      ->accessCheck(FALSE)
      ->condition('ar_type', $this->entity->id())
      ->count()
      ->execute();
    if ($num) {
      $caption = '<p>' . $this->formatPlural(
        $num, 'There is one existing %type record in use. You can not remove this type until you have removed all of the %type records.', '%type is used by @count records. You may not remove %type until you have removed all of the %type records.', ['%type' => $this->entity->label()]
      ) . '</p>';
      $form['#title'] = $this->getQuestion();
      $form['description'] = ['#markup' => $caption];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

}
