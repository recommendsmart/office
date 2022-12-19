<?php

namespace Drupal\flow;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Provider for dynamically provided services by Flow.
 */
class FlowServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    [$version] = explode('.', \Drupal::VERSION, 2);
    if (((int) $version) < 10) {
      // Use normalizers that are compatible with Symfony 4.
      $definition = $container->getDefinition('serializer.normalizer.flow__content_entity');
      $definition->setClass('Drupal\flow\Normalizer\Legacy\FlowContentEntityNormalizer');
      $definition = $container->getDefinition('serializer.normalizer.flow__entity_reference_field_item');
      $definition->setClass('Drupal\flow\Normalizer\Legacy\FlowEntityReferenceFieldItemNormalizer');
    }
  }

}
