<?php

namespace Drupal\personal_notes\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Creates a form to delete your own notes.
 */
class PersonalNotesDeleteForm extends FormBase {

  /**
   * The Current User.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  private Connection $database;

  /**
   * The constructor object of add content.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(AccountProxyInterface $currentUser, Connection $database) {
    $this->currentUser = $currentUser;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'personal_notes_dlet_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($this->currentUser->isAuthenticated()) {
      $this->messenger()
        ->addWarning($this->t('Select the notes to be deleted and submit - this cannot be reversed.'));

      // Get the notes for this user.
      $results = _personal_notes_fetch_content_db();
      $checkboxes = [];
      // Loop through the user's notes to build list of checkboxes.
      foreach ($results as $fields) {
        $checkboxes[$fields->notenum] = $fields->title . ' - ' .
          substr($fields->note, 0, 20);
      }

      $form["delete"] = [
        '#type' => 'checkboxes',
        '#options' => $checkboxes,
      ];

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete Selected Pers. Notes'),
        '#button_type' => 'primary',
      ];
      return $form;
    }
    else {
      $this->messenger()
        ->addStatus($this->t('Please log on in order to delete your notes.'));
      return [];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $num_deleted = 0;
    $delete = $form_state->getValues()['delete'];
    foreach ($delete as $value) {
      if (!empty($value)) {
        $this->database->delete('personal_notes_notes')
          ->condition('notenum', $value)
          ->execute();
        ++$num_deleted;
      }
    }

    if ($num_deleted == 1) {
      $this->messenger()
        ->addMessage($this->t('There was 1 note removed.'));
    }
    else {
      $this->messenger()
        ->addMessage($this->t('There were @count notes removed.', ['@count' => $num_deleted]));
    }

  }

}
