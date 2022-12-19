<?php

namespace Drupal\eca_endpoint\Plugin\Action;

/**
 * Get the content type of the request.
 *
 * @Action(
 *   id = "eca_endpoint_get_request_content_type",
 *   label = @Translation("Request: Get content type")
 * )
 */
class GetRequestContentType extends RequestActionBase {

  /**
   * {@inheritdoc}
   */
  protected function getRequestValue() {
    return $this->getRequest()->getContentType();
  }

}
