<?php

namespace Drupal\access_records\Plugin\Action;

/**
 * Revoke access using access records.
 *
 * @Action(
 *   id = "ar_revoke",
 *   label = "@ar_type: revoke access",
 *   description = "Revokes @subject access from @target by disabling the according @ar_type record.",
 *   deriver = "Drupal\access_records\Plugin\Action\AccessRecordsActionDeriver"
 * )
 */
class AccessRecordsRevoke extends AccessRecordsActionBase {

/**
   * {@inheritdoc}
   */
  public function getUpdateValues(array $context = []): array {
    return ['ar_enabled' => ['value' => 0]];
  }

}
