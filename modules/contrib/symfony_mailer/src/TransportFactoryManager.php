<?php

namespace Drupal\symfony_mailer;

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;

/**
 * Provides the transport factory manager.
 */
class TransportFactoryManager implements TransportFactoryManagerInterface {

  /**
   * List of transport factories.
   *
   * @var \Symfony\Component\Mailer\Transport\TransportFactoryInterface[]
   */
  protected $factories;

  /**
   * Constructs the TransportFactoryManager object.
   */
  public function __construct() {
    $this->factories = iterator_to_array(Transport::getDefaultFactories());
  }

  /**
   * {@inheritdoc}
   */
  public function addFactory(TransportFactoryInterface $factory) {
    $this->factories[] = $factory;
  }

  /**
   * {@inheritdoc}
   */
  public function getFactories() {
    return $this->factories;
  }

}
