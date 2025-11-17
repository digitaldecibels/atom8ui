<?php

namespace Drupal\cash_for_computer_scrap\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;


/**
 * Provides an action to update Front conversation status.
 *
 * @Action(
 *   id = "cash_for_computer_scrap_front_update_status",
 *   label = @Translation("Update Front Conversation Status"),
 *   description = @Translation("Updates a Front conversation status based on node changes."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class FrontUpdateStatusAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use LoggerChannelTrait;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Front API token.
   *
   * @var string
   */
  protected $apiToken;

  /**
   * The conversation ID field.
   *
   * @var string
   */
  protected $conversationIdField;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $config = $container->get('config.factory')->get('cash_for_computer_scrap.settings');

    $instance->apiToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzY29wZXMiOlsic2hhcmVkOioiXSwiaWF0IjoxNzYwNTQzOTU1LCJpc3MiOiJmcm9udCIsInN1YiI6IjNlNmJlYzc1ZjZlN2ZkZTg1Nzg2IiwianRpIjoiMzI3MDdhZmUwNzdhZjA5MiJ9.-gjKMnc5a3fBlGiyl0LezF-c0ZPX0R5cnRgB3IN0QBo";
    $instance->httpClient = $container->get('http_client');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'conversation_id_field' => 'field_external_id',
      'status_mapping' => " Drop Off Scheduled=377044644,
                            Package Processed=377044516,
                            Package Received=377044452,
                            Pending Waiting for Package=377044260,
                            Pickup Schedule Pending=377044324,
                            Pickup Scheduled=377044388,
                            Payment Made=377044580",

    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['conversation_id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Conversation ID Field'),
      '#default_value' => $this->configuration['conversation_id_field'],
      '#required' => TRUE,
    ];

    $form['status_mapping'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Status Mapping'),
      '#description' => $this->t('Map node status to Front conversation status. Example: published=open, unpublished=archived'),
      '#default_value' => $this->configuration['status_mapping'],
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['conversation_id_field'] = $form_state->getValue('conversation_id_field');
    $this->configuration['status_mapping'] = $form_state->getValue('status_mapping');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {
    if (empty($entity) || $entity->getEntityTypeId() !== 'node') {
      $this->getLogger('cash_for_computer_scrap')->error('Front update status action called without a node.');
      return;
    }

    $field = $this->configuration['conversation_id_field'];
    if (!$entity->hasField($field) || $entity->get($field)->isEmpty()) {
      $this->getLogger('cash_for_computer_scrap')->warning('Node @nid has no Front conversation ID.', ['@nid' => $entity->id()]);
      return;
    }

    $conversationId = $entity->get($field)->value;
    $statusMapping = $this->parseMapping($this->configuration['status_mapping']);

    // Determine node status

    $nodeStatus = strtolower(str_replace(['-', ' '], '_', $entity->moderation_state[0]->value));

    $frontStatus = $statusMapping[$nodeStatus] ?? '';

    if(!empty($frontStatus)){
    // PATCH request payload
    $payload = [
      'status_id' => $frontStatus,
    ];



    try {
      $response = $this->httpClient->request('PATCH', "https://api2.frontapp.com/conversations/{$conversationId}", [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiToken,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => $payload,
      ]);

      $this->getLogger('cash_for_computer_scrap')->info('Updated Front conversation @id to status "@status" for node @nid.', [
        '@id' => $conversationId,
        '@status' => $frontStatus,
        '@nid' => $entity->id(),
      ]);

    } catch (RequestException $e) {
      $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();

      $this->getLogger('cash_for_computer_scrap')->error('Failed to update Front status for @nid. Status: @code Error: @error', [
        '@nid' => $entity->id(),
        '@code' => $statusCode,
        '@error' => $errorBody,
      ]);
    }
  }


    // add comment with transition
    $revision_author = $entity->revision_uid[0]->entity->field_first_name[0]->value . ' ' . $entity->revision_uid[0]->entity->field_last_name[0]->value;



    $comment = [
      // 'status_id' => $frontStatus,
     'body' => '**' . ucwords(str_replace('_', ' ', $entity->moderation_state[0]->value)).'** by **' . $revision_author . '**',
    ];

    try {
      // Make the API request to Front.
      $response = $this->httpClient->request('POST', "https://api2.frontapp.com/conversations/{$conversationId}/comments", [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiToken,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => $comment,
        'timeout' => 30,
      ]);

      $responseData = json_decode($response->getBody()->getContents(), TRUE);


    } catch (RequestException $e) {
      $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 'N/A';
      $errorBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();

      $this->getLogger('cash_for_computer_scrap')->error('Failed to create Front conversation for node @nid. Status: @status. Error: @message', [
        '@nid' => $entity->id(),
        '@status' => $statusCode,
        '@message' => $errorBody,
      ]);

      \Drupal::messenger()->addError($this->t('Failed to create Front conversation. Error: @message', ['@message' => $errorBody]));
    } catch (\Exception $e) {
      $this->getLogger('cash_for_computer_scrap')->error('Unexpected error creating Front conversation for node @nid. Error: @message', [
        '@nid' => $entity->id(),
        '@message' => $e->getMessage(),
      ]);

      \Drupal::messenger()->addError($this->t('An unexpected error occurred: @message', ['@message' => $e->getMessage()]));
    }

  }

  /**
   * Helper: Parse status mapping string.
   */
  protected function parseMapping(string $mapping): array {
    $map = [];
    foreach (preg_split('/\r\n|\r|\n/', $mapping) as $line) {
      $line = trim($line);
      if (str_contains($line, '=')) {
        [$key, $val] = array_map('trim', explode('=', $line, 2));
        $map[strtolower(str_replace(['-', ' '], '_', $key))] = $val;
      }
    }
    return $map;
  }

}


