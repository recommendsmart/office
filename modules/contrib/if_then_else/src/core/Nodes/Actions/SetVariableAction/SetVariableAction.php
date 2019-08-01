<?php

namespace Drupal\if_then_else\core\Nodes\Actions\SetVariableAction;

use Drupal\if_then_else\core\Nodes\Actions\Action;
use Drupal\if_then_else\Event\GraphValidationEvent;
use Drupal\if_then_else\Event\NodeSubscriptionEvent;
use Drupal\Component\Utility\Html;
use Drupal\if_then_else\Event\NodeValidationEvent;

/**
 * Set variable action class.
 */
class SetVariableAction extends Action {

  /**
   * {@inheritdoc}
   */
  public static function getName() {
    return 'set_variable_action';
  }

  /**
   * {@inheritdoc}
   */
  public function registerNode(NodeSubscriptionEvent $event) {
    $event->nodes[static::getName()] = [
      'label' => t('Set Variable'),
      'type' => 'action',
      'class' => 'Drupal\\if_then_else\\core\\Nodes\\Actions\\SetVariableAction\\SetVariableAction',
      'library' => 'if_then_else/SetVariableAction',
      'control_class_name' => 'SetVariableActionControl',
      'inputs' => [
        'name' => [
          'label' => t('Name'),
          'description' => t('Input Name.'),
          'sockets' => ['string'],
          'required' => TRUE,
        ],
        'value' => [
          'label' => t('Value'),
          'description' => t('Input Value.'),
          'sockets' => ['string', 'bool', 'number', 'array', 'object.entity'],
          'required' => TRUE,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function validateGraph(GraphValidationEvent $event) {
    $nodes = $event->data->nodes;
    foreach ($nodes as $node) {
      if ($node->data->type == 'value' && $node->data->name == 'text_value') {
        // To check empty input.
        foreach ($node->outputs->text->connections as $connection) {
          if ($connection->input == 'name' &&  (!property_exists($node->data, 'value') || empty($node->data->value))) {
            $event->errors[] = t('Enter name in "@node_name".', ['@node_name' => $node->name]);

          }
          if ($connection->input == 'value' &&  (!property_exists($node->data, 'value') || empty($node->data->value))) {
            $event->errors[] = t('Enter value in "@node_name".', ['@node_name' => $node->name]);
          }
        }
      }
    }
  }

  /**
   * Validation function.
   */
  public function validateNode(NodeValidationEvent $event) {
    $data = $event->node->data;
    if (!property_exists($data, 'valueText') || empty($data->valueText)) {
      $event->errors[] = t('Enter config object name in "@node_name".', ['@node_name' => $event->node->name]);
    }
    if (property_exists($data, 'valueText') || !empty($data->valueText)) {
      $config_object_name = $data->valueText;
      // The name must be namespaced by owner.
      if (strpos($config_object_name, '.') === FALSE) {
        $event->errors[] = t('Missing namespace in Config object name "@setting_name". Expected pattern foo.foo or example.settings', ['@setting_name' => $config_object_name]);

      }

      // The name must be shorter than Config::MAX_NAME_LENGTH characters.
      if (strlen($config_object_name) > 250) {
        $event->errors[] = t('Config object name "@setting_name" exceeds maximum allowed length of 250 characters.', ['@setting_name' => $config_object_name]);

      }

      // The name must not contain any of the following characters:
      // : ? * < > " ' / \.
      if (preg_match('/[:?*<>"\'\\/\\\\]/', $config_object_name)) {
        $event->errors[] = t('Invalid character in Config object name "@setting_name".', ['@setting_name' => $config_object_name]);

      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function process() {
    $name = $this->inputs['name'];
    $value = $this->inputs['value'];
    $config_object_name = Html::escape($this->data->valueText);

    \Drupal::configFactory()->getEditable($config_object_name)
      ->set($name, $value)
      ->save();

  }

}
