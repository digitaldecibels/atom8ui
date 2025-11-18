<?php

namespace Drupal\atom8\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\atom8\Service\Atom8Client;

class WorkflowController extends ControllerBase {

  protected Atom8Client $client;

  public function __construct(Atom8Client $client) {
    $this->client = $client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('atom8.client')
    );
  }

  /**
   * Load workflows with dynamic override values.
   *
   * Accepts ?url=...&apikey=...&endpoint=/workflows
   */

  public function list(Request $request) {
  $url = $request->query->get('url');
  $apikey = $request->query->get('apikey');
  $endpoint = $request->query->get('endpoint') ?? '/workflows';

  $data = $this->client->getWorkflows($url, $apikey, $endpoint);
 


//        return new JsonResponse([
//     'url' => $url,
//     'apikey' => $apikey,
//     'endpoint' => $endpoint,
//     'message' => 'This is a basic debug response before calling n8n.',
//   ]);
 
  return new JsonResponse($data ?? ['error' => 'Unable to fetch workflows']);
}

}


