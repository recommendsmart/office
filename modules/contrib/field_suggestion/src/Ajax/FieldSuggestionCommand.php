<?php

namespace Drupal\field_suggestion\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Defines an AJAX command to select a suggestion.
 *
 * @ingroup ajax
 */
class FieldSuggestionCommand implements CommandInterface {

  /**
   * The field name.
   *
   * @var string
   */
  protected $name;

  /**
   * The suggestion offset.
   *
   * @var int
   */
  protected $delta;

  /**
   * The name of the main property.
   *
   * @var string
   */
  protected $property;

  /**
   * Constructs an OpenDialogCommand object.
   *
   * @param string $name
   *   The field name.
   * @param int $delta
   *   The suggestion offset.
   * @param string $property
   *   The name of the main property.
   */
  public function __construct($name, $delta, $property) {
    $this->name = $name;
    $this->delta = $delta;
    $this->property = $property;
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'fieldSuggestion',
      'name' => $this->name,
      'delta' => $this->delta,
      'property' => $this->property,
    ];
  }

}
