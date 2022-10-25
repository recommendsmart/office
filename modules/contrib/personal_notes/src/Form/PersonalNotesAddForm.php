<?php

namespace Drupal\personal_notes\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Add a personal note.
 */
class PersonalNotesAddForm extends FormBase {

  /**
   * The Current User.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * The time info.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  private TimeInterface $time;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time info.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(AccountProxyInterface $currentUser, TimeInterface $time, Connection $database) {
    $this->currentUser = $currentUser;
    $this->time = $time;
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('datetime.time'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'personal_notes_add_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, AccountInterface $user = NULL): array {

    if ($this->currentUser->isAuthenticated() && !empty($user)) {
      $form['note_user'] = [
        '#type' => 'markup',
        '#markup' => "User: " . $user->getAccountName(),
      ];
      $form['note_title'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Note Title'),
        '#size' => 30,
        '#maxlength' => 24,
        '#required' => TRUE,
      ];
      $form['note_content'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Note Content'),
        '#rows' => 3,
        '#cols' => 40,
        '#resizable' => TRUE,
        '#required' => TRUE,
      ];
      $form['note_member'] = [
        '#type' => 'hidden',
        '#value' => $user->id(),
      ];
      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Save'),
        '#button_type' => 'primary',
      ];
    }

    else {
      $this->messenger()
        ->addStatus($this->t('Please login to add personal notes.'));
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve field data from triggered user.
    $uid = $form_state->getValues()['note_member'];
    $title = $form_state->getValues()['note_title'];
    $note = $form_state->getValues()['note_content'];
    $time = $this->time->getCurrentTime();

    $fields = [
      'uid' => $uid,
      'title' => $title,
      'note' => $note,
      'created' => $time,
    ];
    // Add new note to the database.
    $this->database->insert('personal_notes_notes')
      ->fields($fields)
      ->execute();
    $this->messenger()
      ->addStatus($this->t('Your note was added.'));
  }

}
