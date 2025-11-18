<?php

namespace Drupal\atom8\Controller;

use Drupal\Core\Controller\ControllerBase;

class ApiKeys extends ControllerBase {

  /**
   * Returns a list of API keys.
   */
  public function list() {
    // Return a render array, table, or markup here.
    return [
      '#markup' => 'API Keys Page Content',
      '#theme' => 'atom8_api_keys',
    ];
  }
}