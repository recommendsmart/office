<?php

namespace Drupal\storage;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\storage\Entity\StorageType;
use Drupal\storage\Entity\StorageTypeInterface;

/**
 * Provides dynamic permissions for Storage entities of different types.
 *
 * @ingroup storage
 */
class StoragePermissions {

  use StringTranslationTrait;

  /**
   * Returns an array of Storage entity permissions.
   *
   * @return array
   *   The Storage by bundle permissions.
   *   @see \Drupal\user\PermissionHandlerInterface::getPermissions()
   */
  public function generatePermissions() {
    $perms = [];

    foreach (StorageType::loadMultiple() as $type) {
      $perms += $this->buildPermissions($type);
    }

    return $perms;
  }

  /**
   * Returns a list of Storage entity permissions for a given Storage type.
   *
   * @param \Drupal\storage\Entity\StorageTypeInterface $type
   *   The Storage type.
   *
   * @return array
   *   An associative array of permission names and descriptions.
   */
  protected function buildPermissions(StorageTypeInterface $type) {
    $type_id = $type->id();
    $type_params = ['%type_name' => $type->label()];

    return array_map(static function ($perm) use ($type) {
        $perm['dependencies'][$type->getConfigDependencyKey()][] = $type->getConfigDependencyName();
        return $perm;
      },
      [
      "add $type_id storage entities" => [
        'title' => $this->t('Add new %type_name Storage entities', $type_params),
      ],
      "view published $type_id storage entities" => [
        'title' => $this->t('View published %type_name Storage entities.', $type_params),
      ],
      "view unpublished $type_id storage entities" => [
        'title' => $this->t('View unpublished %type_name Storage entities.', $type_params),
        'restrict access' => TRUE,
      ],
      "view own unpublished $type_id storage entities" => [
        'title' => $this->t('View own unpublished %type_name Storage entities.', $type_params),
      ],
      "edit own $type_id storage entities" => [
        'title' => $this->t('Edit own %type_name Storage entities', $type_params),
      ],
      "edit any $type_id storage entities" => [
        'title' => $this->t('Edit any %type_name Storage entities', $type_params),
        'restrict access' => TRUE,
      ],
      "delete own $type_id storage entities" => [
        'title' => $this->t('Delete own %type_name Storage entities', $type_params),
      ],
      "delete any $type_id storage entities" => [
        'title' => $this->t('Delete any %type_name Storage entities', $type_params),
        'restrict access' => TRUE,
      ],
      "view $type_id storage revisions" => [
        'title' => $this->t('View %type_name Storage revisions', $type_params),
        'description' => t('To view a revision, you also need permission to view the Storage item.'),
      ],
      "revert $type_id storage revisions" => [
        'title' => $this->t('Revert %type_name Storage revisions', $type_params),
        'description' => t('To revert a revision, you also need permission to edit the Storage item.'),
      ],
      "delete $type_id storage revisions" => [
        'title' => $this->t('Delete %type_name Storage revisions', $type_params),
        'description' => $this->t('To delete a revision, you also need permission to delete the Storage item.'),
        'restrict access' => TRUE,
      ],
    ]);
  }

}
