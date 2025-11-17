<?php

namespace Drupal\cash_for_computer_scrap\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * Provides a Front Conversation Action plugin.
 *
 * @Action(
 *   id = "cash_for_computer_scrap_front_conversation",
 *   label = @Translation("Create Front Conversation"),
 *   description = @Translation("Creates a conversation in Front from node data."),
 *   eca_version_introduced = "1.0.0",
 *   type = "entity"
 * )
 */
class FrontConversationAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
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
   * The Front inbox ID.
   *
   * @var string
   */
  protected $inboxId;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Get the Front API credentials from your module's configuration.
    $config = $container->get('config.factory')->get('cash_for_computer_scrap.settings');

    $instance->apiToken = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzY29wZXMiOlsic2hhcmVkOioiXSwiaWF0IjoxNzYwNTQzOTU1LCJpc3MiOiJmcm9udCIsInN1YiI6IjNlNmJlYzc1ZjZlN2ZkZTg1Nzg2IiwianRpIjoiMzI3MDdhZmUwNzdhZjA5MiJ9.-gjKMnc5a3fBlGiyl0LezF-c0ZPX0R5cnRgB3IN0QBo";
    $instance->inboxId = '33279844';
    $instance->httpClient = $container->get('http_client');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'subject_template' => '[Lot] [node:title]',
      'body_template' => '',
      'tags' => 'lot-created',
      'custom_fields' => '',
      'conversation_id_field' => 'field_external_id',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $form['subject_template'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject Template'),
      '#description' => $this->t('The subject line for the Front conversation. Tokens are supported (use [node:title] format).'),
      '#default_value' => $this->configuration['subject_template'],
      '#required' => TRUE,
    ];

    $form['body_template'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message Body Template'),
      '#description' => $this->t('The message content for the Front conversation. Tokens are supported (use [node:field_name] format). Leave empty for auto-generated content.'),
      '#default_value' => $this->configuration['body_template'],
      '#rows' => 10,
    ];

    $form['tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tags'),
      '#description' => $this->t('Comma-separated list of tags to apply to the conversation (e.g., lot-created,urgent).'),
      '#default_value' => $this->configuration['tags'],
    ];

    $form['custom_fields'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom Fields Mapping'),
      '#description' => $this->t('Map node fields to Front custom fields in format: cf_node_id=[node:nid],cf_price=[node:field_price]. One per line. Tokens are supported.'),
      '#default_value' => $this->configuration['custom_fields'],
      '#rows' => 5,
    ];

    $form['conversation_id_field'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Conversation ID Field'),
      '#description' => $this->t('The machine name of the field where the Front conversation ID will be saved (e.g., field_front_conversation_id).'),
      '#default_value' => $this->configuration['conversation_id_field'],
      '#required' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['subject_template'] = $form_state->getValue('subject_template');
    $this->configuration['body_template'] = $form_state->getValue('body_template');
    $this->configuration['tags'] = $form_state->getValue('tags');
    $this->configuration['custom_fields'] = $form_state->getValue('custom_fields');
    $this->configuration['conversation_id_field'] = $form_state->getValue('conversation_id_field');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {


    // ECA passes the entity directly in the execute method
    // If still null, log and return
    if (empty($entity)) {
      \Drupal::messenger()->addError($this->t('No entity provided to Front conversation action.'));
      $this->getLogger('cash_for_computer_scrap')->error('Front conversation action called without entity.');
      return;
    }

    // Validate it's a node
    if (!method_exists($entity, 'getEntityTypeId') || $entity->getEntityTypeId() !== 'node') {
      \Drupal::messenger()->addError($this->t('Front conversation can only be created for nodes. Got: @type', [
        '@type' => is_object($entity) && method_exists($entity, 'getEntityTypeId') ? $entity->getEntityTypeId() : gettype($entity)
      ]));
      return;
    }

    // Check if API token and inbox ID are configured.
    if (empty($this->apiToken) || empty($this->inboxId)) {
      $this->getLogger('cash_for_computer_scrap')->error('Front API token or inbox ID not configured.');
      \Drupal::messenger()->addError($this->t('Front API is not properly configured.'));
      return;
    }

    // Prepare token data
    $token_data = ['node' => $entity];

    // Render tokens in the subject and body.
    $subject = $this->tokenService->replace($this->configuration['subject_template'], $token_data);
    $body = $this->tokenService->replace($this->configuration['body_template'], $token_data);

    // If body is empty, generate a default one.
    if (empty(trim($body))) {
      $body = $this->generateDefaultBody($entity);
    }

    // Parse tags.
    $tags = array_map('trim', explode(',', $this->configuration['tags']));
    $tags = array_filter($tags);

    // Parse and build custom fields.
    $customFields = $this->buildCustomFields($entity, $token_data);



    // Build the Front API request payload.
    $payload = [
      'type' => 'discussion',
      'comment' => [
        'body' => $body,
      ],
      'inbox_id' => $this->inboxId,
      'subject' => $subject,
    ];


      \Drupal::logger('cash_for_computer_scrap')->debug('Front payload JSON: @json', [
        '@json' => json_encode($payload, JSON_PRETTY_PRINT),
      ]);

    // Add tags if present.
    if (!empty($tags)) {
      $payload['tags'] = $tags;
    }

    // Add custom fields if present.
    if (!empty($customFields)) {
      $payload['custom_fields'] = $customFields;
    }

    try {
      // Make the API request to Front.
      $response = $this->httpClient->request('POST', 'https://api2.frontapp.com/conversations', [
        'headers' => [
          'Authorization' => 'Bearer ' . $this->apiToken,
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'json' => $payload,
        'timeout' => 30,
      ]);

      $responseData = json_decode($response->getBody()->getContents(), TRUE);

      // Extract the conversation ID from the response.
      if (isset($responseData['id'])) {
        $conversationId = $responseData['id'];

        // Save the conversation ID to the node.
        $fieldName = $this->configuration['conversation_id_field'];
        if ($entity->hasField($fieldName)) {
          $entity->set($fieldName, $conversationId);
          $entity->save();

          $this->getLogger('cash_for_computer_scrap')->info('Front conversation @id created for node @nid.', [
            '@id' => $conversationId,
            '@nid' => $entity->id(),
          ]);

          \Drupal::messenger()->addStatus($this->t('Front conversation created successfully. ID: @id', ['@id' => $conversationId]));
        } else {
          $this->getLogger('cash_for_computer_scrap')->warning('Field @field does not exist on node @nid. Conversation ID @id not saved.', [
            '@field' => $fieldName,
            '@nid' => $entity->id(),
            '@id' => $conversationId,
          ]);
          \Drupal::messenger()->addWarning($this->t('Conversation created but field @field does not exist to save ID.', ['@field' => $fieldName]));
        }
      } else {
        $this->getLogger('cash_for_computer_scrap')->error('Front API response did not contain conversation ID.');
        \Drupal::messenger()->addError($this->t('Conversation may have been created but ID was not returned.'));
      }

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
   * Generates a default message body from node data.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node entity.
   *
   * @return string
   *   The generated message body.
   */
  protected function generateDefaultBody($entity): string {
    $nodeUrl = $entity->toUrl('canonical', ['absolute' => TRUE])->toString();
    $author = $entity->getOwner()->getDisplayName();
    $publishStatus = $entity->isPublished() ? 'Published' : 'Unpublished';

    $body = "New lot created\n\n";
    $body .= "Title: " . $entity->label() . "\n";
    $body .= "URL: " . $nodeUrl . "\n";
    $body .= "Author: " . $author . "\n";
    $body .= "Status: " . $publishStatus . "\n";
    $body .= "Node ID: " . $entity->id() . "\n";

    return $body;
  }

  /**
   * Builds custom fields array from configuration.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The node entity.
   * @param array $token_data
   *   The token data array.
   *
   * @return array
   *   The custom fields array.
   */
  protected function buildCustomFields($entity, array $token_data): array {
    $customFields = [];
    $customFieldsConfig = trim($this->configuration['custom_fields']);

    if (empty($customFieldsConfig)) {
      return $customFields;
    }

    $lines = explode("\n", $customFieldsConfig);

    foreach ($lines as $line) {
      $line = trim($line);
      if (empty($line)) {
        continue;
      }

      if (strpos($line, '=') !== FALSE) {
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Replace tokens in the value.
        $value = $this->tokenService->replace($value, $token_data);

        if (!empty($key) && !empty($value)) {
          $customFields[$key] = $value;
        }
      }
    }

    return $customFields;
  }
}