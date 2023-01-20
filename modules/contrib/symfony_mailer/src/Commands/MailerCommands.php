<?php

namespace Drupal\symfony_mailer\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface;
use Drush\Commands\DrushCommands;

/**
 * Symfony Mailer drush commands.
 */
class MailerCommands extends DrushCommands {

  /**
   * The email builder manager.
   *
   * @var \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface
   */
  protected $builderManager;

  /**
   * Constructs the MailerCommands object.
   *
   * @param \Drupal\symfony_mailer\Processor\EmailBuilderManagerInterface $email_builder_manager
   *   The email builder manager.
   */
  public function __construct(EmailBuilderManagerInterface $email_builder_manager) {
    $this->builderManager = $email_builder_manager;
  }

  /**
   * Imports legacy config.
   *
   * @param string $id
   *   EmailBuilder ID to import.
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @option skip
   *   Skip the import.
   *
   * @command mailer:import
   */
  public function import(string $id = NULL, array $options = ['skip' => FALSE]) {
    if ($options['skip']) {
      $this->builderManager->setImportState($id, EmailBuilderManagerInterface::IMPORT_SKIPPED);
    }
    elseif ($id) {
      $this->builderManager->import($id);
    }
    else {
      $this->builderManager->importAll();
    }
  }

  /**
   * Shows information about legacy config importing.
   *
   * @param array $options
   *   An associative array of options whose values come from cli, aliases,
   *   config, etc.
   *
   * @command mailer:import-info
   * @field-labels
   *   name: Name
   *   state_name: State
   *   warning: Warning
   */
  public function importInfo(array $options = ['format' => 'table']) {
    $info = $this->builderManager->getImportInfo();
    return new RowsOfFields($info);
  }

}
