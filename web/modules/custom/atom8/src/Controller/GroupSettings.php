<?php

namespace Drupal\atom8\Controller;

use Drupal\Core\Controller\ControllerBase;

class GroupSettings extends ControllerBase {

  /**
   * Returns a list of API keys.
   */
  public function settings($gid) {


    // Return a render array, table, or markup here.
    return [
      '#markup' => 'Group Settings',
      '#theme' => 'atom8_group_settings',
      '#gid'=> $gid
    ];
  }
}