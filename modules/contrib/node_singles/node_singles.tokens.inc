<?php

/**
 * @file
 * Tokens for the node singles module.
 */

use Drupal\node\NodeInterface;

/**
 * Implements @see hook_token_info().
 */
function node_singles_token_info(): array {
  $types['node_singles'] = [
    'name' => t('Node Singles'),
    'description' => t('Node Singles tokens'),
  ];

  // Get all singles.
  $singles = [];
  /** @var \Drupal\Core\Entity\EntityTypeInterface $type */
  foreach (\Drupal::service('node_singles')->getAllSingles() as $type) {
    $singles[$type->id() . ':url'] = [
      'name' => $type->label() . ' URL',
      'description' => t('The alias of the node single.'),
    ];
  }

  return [
    'types' => $types,
    'tokens' => [
      'node_singles' => $singles,
    ],
  ];
}

/**
 * Implements @see hook_tokens().
 */
function node_singles_tokens(string $type, array $tokens, array $data = [], array $options = []): array {
  $replacements = [];

  if ($type !== 'node_singles') {
    return $replacements;
  }

  /** @var \Drupal\node_singles\Service\NodeSinglesInterface $singles */
  $singles = \Drupal::service('node_singles');
  /** @var \Drupal\path_alias\AliasManagerInterface $pathAliasManager */
  $pathAliasManager = \Drupal::service('path_alias.manager');

  foreach ($tokens as $name => $original) {
    $split = explode(':', $name);

    // Load the content type.
    $node = $singles->getSingleByBundle($split[0]);

    if (!$node instanceof NodeInterface) {
      continue;
    }

    if ($split[1] === 'url') {
      $canonical = '/node/' . $node->id();
      $alias = $pathAliasManager->getAliasByPath($canonical, $options['langcode'] ?? NULL);

      if ($alias !== $canonical) {
        $replace = $alias;
      }
    }

    $replacements[$original] = $replace ?? '';
  }

  return $replacements;
}
