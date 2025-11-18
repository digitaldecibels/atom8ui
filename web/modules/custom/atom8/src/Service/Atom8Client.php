<?php

namespace Drupal\atom8\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Atom8 client for querying an n8n API dynamically.
 */
class Atom8Client {

  protected ClientInterface $httpClient;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   *   Guzzle HTTP client.
   */
  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  /**
   * Fetch workflows from a given n8n instance dynamically.
   *
   * @param string $base_url
   *   Base URL of n8n (e.g. https://n8n.example.com).
   * @param string|null $api_key
   *   Optional n8n API key (passed via X-N8N-API-KEY).
   * @param string $endpoint_path
   *   API endpoint path (default '/workflows').
   * @param int $timeout
   *   Timeout in seconds (default 15).
   *
   * @return array|null
   *   Decoded JSON array of workflows, or NULL on failure.
   */
  public function getWorkflows(string $base_url, ?string $api_key = NULL, string $endpoint_path = '/workflows', int $timeout = 15): ?array {
    $url = rtrim($base_url, '/') . '/' . ltrim($endpoint_path, '/');

    // n8n requires X-N8N-API-KEY instead of Authorization.
    $headers = [
      'Accept' => 'application/json',
    ];
    if (!empty($api_key)) {
      $headers['X-N8N-API-KEY'] = $api_key;
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        'headers' => $headers,
        'timeout' => $timeout,
        'http_errors' => TRUE,
      ]);

      $body = (string) $response->getBody();
      $data = json_decode($body, TRUE);

      if (json_last_error() !== JSON_ERROR_NONE) {
        \Drupal::logger('atom8')->error('JSON decode error: @err for @url', [
          '@err' => json_last_error_msg(),
          '@url' => $url,
        ]);
        return NULL;
      }

      return $data;
    }
    catch (GuzzleException $e) {
      \Drupal::logger('atom8')->error('HTTP request failed for @url: @msg', [
        '@url' => $url,
        '@msg' => $e->getMessage(),
      ]);
      return NULL;
    }
    catch (\Throwable $t) {
      \Drupal::logger('atom8')->error('Unexpected error fetching @url: @msg', [
        '@url' => $url,
        '@msg' => $t->getMessage(),
      ]);
      return NULL;
    }
  }

}
