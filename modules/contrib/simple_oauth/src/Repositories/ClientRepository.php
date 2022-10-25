<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Drupal\simple_oauth\Entities\ClientEntity;

/**
 * The client repository.
 */
class ClientRepository implements ClientRepositoryInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The password hashing service.
   *
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected PasswordInterface $passwordChecker;

  /**
   * Constructs a ClientRepository object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PasswordInterface $password_checker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordChecker = $password_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientEntity($client_identifier) {
    return new ClientEntity($this->getClientDrupalEntity($client_identifier));
  }

  /**
   * Get the client Drupal entity.
   *
   * @param string $client_identifier
   *   Client ID.
   *
   * @return \Drupal\consumers\Entity\Consumer
   *   The loaded drupal consumer (client) entity.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getClientDrupalEntity(string $client_identifier) {
    $client_drupal_entities = $this->entityTypeManager
      ->getStorage('consumer')
      ->loadByProperties(['client_id' => $client_identifier]);

    // Check if the client is registered.
    if (empty($client_drupal_entities)) {
      return NULL;
    }
    return reset($client_drupal_entities);
  }

  /**
   * {@inheritdoc}
   */
  public function validateClient($client_identifier, $client_secret, $grant_type) {
    $client_drupal_entity = $this->getClientDrupalEntity($client_identifier);
    if (!$client_drupal_entity) {
      return FALSE;
    }

    // For the client credentials grant type a default user is required.
    if ($grant_type === 'client_credentials' && !$client_drupal_entity->get('user_id')->entity) {
      throw OAuthServerException::serverError('Invalid default user for client.');
    }

    $secret_field = $client_drupal_entity->get('secret');

    // Determine whether the client is public. Note that if a client secret is
    // provided it should be validated, even if the client is non-confidential.
    // The client_credentials grant is specifically special-cased.
    // @see https://datatracker.ietf.org/doc/html/rfc6749#section-4.4
    if (!$client_drupal_entity->get('confidential')->value &&
      $secret_field->isEmpty() &&
      empty($client_secret) &&
      $grant_type !== 'client_credentials') {
      return TRUE;
    }

    // Check if a secret has been provided for this client and validate it.
    // @see https://datatracker.ietf.org/doc/html/rfc6749#section-3.2.1
    return !(!$secret_field->isEmpty()) || $client_secret && $this->passwordChecker->check($client_secret, $client_drupal_entity->get('secret')->value);
  }

}
