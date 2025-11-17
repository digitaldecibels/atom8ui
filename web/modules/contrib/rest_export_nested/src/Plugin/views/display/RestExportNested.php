<?php

namespace Drupal\rest_export_nested\Plugin\views\display;

use Drupal\rest\Plugin\views\display\RestExport;
use function GuzzleHttp\json_encode;
use Drupal\Core\Render\RenderContext;
use Drupal\Component\Utility\Html;
use Drupal\views\Render\ViewsRenderPipelineMarkup;

/**
 * The plugin that handles Data response callbacks for REST resources.
 *
 * @ingroup views_display_plugins
 *
 * @ViewsDisplay(
 *   id = "rest_export_nested",
 *   title = @Translation("REST export nested"),
 *   help = @Translation("Create a REST export resource which supports nested JSON."),
 *   uses_route = TRUE,
 *   admin = @Translation("REST export nested"),
 *   returns_response = TRUE
 * )
 */
class RestExportNested extends RestExport {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = [];
    $build['#markup'] = $this->renderer->executeInRenderContext(new RenderContext(), function () {
      return $this->view->style_plugin->render();
    });

    // Decode results.
    $results = \GuzzleHttp\json_decode($build['#markup']);

    // The Core Serializer returns an array of rows. Custom can create an object
    // with a 'rows' parameter.
    if (gettype($results) === 'object') {
      // @todo Allow rows name property to be customizable.
      $rows = 'rows';
      $results->$rows = $this->flatten($results->$rows);
    }
    else {
      $results = $this->flatten($results);
    }

    // Convert back to JSON.
    $build['#markup'] = json_encode($results, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

    $this->view->element['#content_type'] = $this->getMimeType();
    $this->view->element['#cache_properties'][] = '#content_type';

    // Encode and wrap the output in a pre tag if this is for a live preview.
    if (!empty($this->view->live_preview)) {
      $build['#prefix'] = '<pre>';
      $build['#plain_text'] = $build['#markup'];
      $build['#suffix'] = '</pre>';
      unset($build['#markup']);
    }
    elseif ($this->view->getRequest()->getFormat($this->view->element['#content_type']) !== 'html') {
      // This display plugin is primarily for returning non-HTML formats.
      // However, we still invoke the renderer to collect cacheability metadata.
      // Because the renderer is designed for HTML rendering, it filters
      // #markup for XSS unless it is already known to be safe, but that filter
      // only works for HTML. Therefore, we mark the contents as safe to bypass
      // the filter. So long as we are returning this in a non-HTML response
      // (checked above), this is safe, because an XSS attack only works when
      // executed by an HTML agent.
      // @todo Decide how to support non-HTML in the render API in
      //   https://www.drupal.org/node/2501313.
      $build['#markup'] = ViewsRenderPipelineMarkup::create($build['#markup']);
    }

    parent::applyDisplayCacheabilityMetadata($build);

    return $build;
  }

  /**
   * Flattens nested view.
   *
   * @param array $data
   *   Nested view data.
   *
   * @return array
   *   Flattened view data.
   */
  protected function flatten(array $data) {
    // Loop through rows results and fields.
    foreach ($data as $key => $result) {
      foreach ($result as $property => $value) {
        // Check if the field can be decoded using PHP's json_decode().
        if (is_string($value)) {
          if (json_decode($value) !== NULL) {
            // If so, use Guzzle to decode the JSON and add it to the data.
            $data[$key]->$property = \GuzzleHttp\json_decode($value);
          }
          elseif (json_decode(Html::decodeEntities($value)) !== NULL) {
            $data[$key]->$property = \GuzzleHttp\json_decode(Html::decodeEntities($value));
          }
        }
        // Special null handling.
        if (is_string($value) && $value === 'null') {
          $data[$key]->$property = NULL;
        }
      }
    }
    return $data;
  }

}
