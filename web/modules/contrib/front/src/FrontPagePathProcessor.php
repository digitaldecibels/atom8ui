<?php

namespace Drupal\front_page;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processes outbound paths to add custom logic for front page redirects.
 *
 * @package Drupal\front_page
 */
class FrontPagePathProcessor implements OutboundPathProcessorInterface {

  /**
   * An immutable config object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  private ImmutableConfig $config;

  /**
   * Constructs the path processor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config factory service.
   */
  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('front_page.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL) {
    if ($path == '/main') {
      $path = '';
    }

    $new_path = $this->config->get('home_link_path', '');
    if (($path === '/<front>' || empty($path)) && !empty($new_path)) {
      $path = '/' . $new_path;
    }
    return $path;
  }

}
