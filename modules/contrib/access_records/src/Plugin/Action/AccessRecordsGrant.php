<?php

namespace Drupal\access_records\Plugin\Action;

/**
 * Grant access using access records.
 *
 * @Action(
 *   id = "ar_grant",
 *   label = "@ar_type: grant access",
 *   description = "Grants @subject access to @target by enabling an according @ar_type record.",
 *   deriver = "Drupal\access_records\Plugin\Action\AccessRecordsActionDeriver"
 * )
 */
class AccessRecordsGrant extends AccessRecordsActionBase {

  /**
   * {@inheritdoc}
   */
  public function getUpdateValues(array $context = []): array {
    return ['ar_enabled' => ['value' => 1]];
  }

}
