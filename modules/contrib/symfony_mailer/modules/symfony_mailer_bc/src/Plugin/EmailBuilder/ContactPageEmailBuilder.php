<?php

namespace Drupal\symfony_mailer_bc\Plugin\EmailBuilder;

use Drupal\contact\Entity\ContactForm;
use Drupal\contact\MessageInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\symfony_mailer\EmailInterface;
use Drupal\symfony_mailer\Entity\MailerPolicy;

/**
 * Defines the Email Builder plug-in for contact module page forms.
 *
 * @EmailBuilder(
 *   id = "contact_form",
 *   sub_types = {
 *     "mail" = @Translation("Message"),
 *     "copy" = @Translation("Sender copy"),
 *     "autoreply" = @Translation("Auto-reply"),
 *   },
 *   has_entity = TRUE,
 *   proxy = TRUE,
 *   common_adjusters = {"email_subject", "email_body", "email_to"},
 *   import = @Translation("Contact form recipients"),
 * )
 *
 * @todo Notes for adopting Symfony Mailer into Drupal core. This builder can
 * set langcode, to, reply-to so the calling code doesn't need to.
 */
class ContactPageEmailBuilder extends ContactEmailBuilderBase {

  /**
   * Saves the parameters for a newly created email.
   *
   * @param \Drupal\symfony_mailer\EmailInterface $email
   *   The email to modify.
   * @param \Drupal\contact\MessageInterface $message
   *   Submitted message entity.
   * @param \Drupal\Core\Session\AccountInterface $sender
   *   The sender.
   */
  public function createParams(EmailInterface $email, MessageInterface $message = NULL, AccountInterface $sender = NULL) {
    assert($sender != NULL);
    $email->setParam('contact_message', $message)
      ->setParam('sender', $sender);
  }

  /**
   * {@inheritdoc}
   */
  public function build(EmailInterface $email) {
    parent::build($email);
    $email->setVariable('form', $email->getEntity()->label())
      ->setVariable('form_url', Url::fromRoute('<current>')->toString());

    if ($email->getSubType() == 'autoreply') {
      $email->setBody($email->getEntity()->getReply());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function import() {
    $helper = $this->helper();

    foreach (ContactForm::loadMultiple() as $id => $form) {
      if ($id != 'personal') {
        $addresses = $helper->parseAddress(implode(',', $form->getRecipients()));
        $config['email_to'] = $helper->policyFromAddresses($addresses);
        MailerPolicy::import("contact_form.mail.$id", $config);
      }
    }
  }

}
