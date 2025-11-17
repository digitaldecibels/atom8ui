<?php

namespace Drupal\cash_for_computer_scrap\Plugin\Action;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Twilio\Rest\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Provides a Twilio SMS Action plugin.
 *
 * @Action(
 * id = "cash_for_computer_scrap_twilio_sms",
 * label = @Translation("Send SMS via Twilio"),
 * description = @Translation("Sends an SMS message using the Twilio API."),
 * eca_version_introduced = "1.0.0",
 * type = "system"
 * )
 */
class TwilioSmsAction extends ConfigurableActionBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;
  use LoggerChannelTrait;

  /**
   * The Twilio REST client.
   *
   * @var \Twilio\Rest\Client
   */
  protected $twilioClient;


  /**
   * The SMS footer configuration value.
   *
   * @var string
   */
  protected $smsFooter;


 /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    // Get the Twilio credentials and SMS footer from your module's configuration.
    $config = $container->get('config.factory')->get('cash_for_computer_scrap.settings');

    $accountSid = $config->get('twilio_sid');
    $authToken = $config->get('twilio_auth_token');
    $from_number = $config->get('twilio_from_number');

    // Store the SMS footer value in a protected property for later use.
    $instance->smsFooter = $config->get('sms_footer.value');

    // Create the Twilio client instance using the retrieved credentials.
    $twilio_client = new Client($accountSid, $authToken);

    // Use a setter method to inject the client into the plugin instance.
    $instance->setTwilioClient($twilio_client);

    return $instance;
  }

  /**
   * Sets the Twilio client.
   *
   * @param \Twilio\Rest\Client $twilio_client
   * The Twilio client.
   */
  public function setTwilioClient(Client $twilio_client) {
    $this->twilioClient = $twilio_client;
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'to' => '',
      'body' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {


    $form['to'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Recipient Phone Number'),
      '#description' => $this->t('The phone number to send the SMS to (e.g., +15551234567). Tokens are supported.'),
      '#default_value' => $this->configuration['to'],
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message Body'),
      '#description' => $this->t('The message content. Tokens are supported.'),
      '#default_value' => $this->configuration['body'],
      '#required' => TRUE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['to'] = $form_state->getValue('to');
    $this->configuration['body'] = $form_state->getValue('body');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function execute(mixed $entity = NULL): void {


    // do not send if SMS is disabled
   if($entity->uid->entity->field_sms->value != 1){

    return;
   }


    $token = $this->tokenService;

    // Render tokens in the recipient and message body.
    $to = $token->replace($this->configuration['to']);
    $body = $token->replace($this->configuration['body']);
    $body = $this->SMS_format($body);

    // If no message do nothing
    if(empty($body)){
       \Drupal::messenger()->addError($this->t('The SMS body was empty'));
      return;
    }

    $formattedBodyWithFooter = $body . "\n\n" . $this->SMS_format($this->smsFooter);
    $final_body = $this->SMS_format($formattedBodyWithFooter);


    try {
      $this->twilioClient->messages->create(
        $to,
        [
          'from' => '+15702794905',
          'body' => $final_body ,
        ]
      );

      // log message
      // $this->getLogger('cash_for_computer_scrap')->info('SMS sent to @to.', ['@to' => $to]);

      \Drupal::messenger()->addStatus($this->t('SMS sent to @to.', ['@to' => $to]));
    } catch (\Exception $e) {
      $this->getLogger('cash_for_computer_scrap')->error('Failed to send SMS to @to. Error: @message', [
        '@to' => $to,
        '@message' => $e->getMessage(),
      ]);
      \Drupal::messenger()->addError($this->t('Failed to send SMS to @to. Error: @message', ['@to' => $to, '@message' => $e->getMessage()]));
    }


  }


  /**
   * Formats HTML from a WYSIWYG editor for SMS.
   *
   * @param string $string
   * The raw HTML string.
   *
   * @return string
   * The formatted plain text string for SMS.
   */
  protected function SMS_format(string $string): string {
    // Your raw HTML from the WYSIWYG editor
    $rawHtml = $string;

    // 1. Convert <br> and </p> tags to newlines before stripping
    $processedHtml = str_replace(['<br>', '</p>'], ["\n", "\n\n"], $rawHtml);

    // 2. Strip all remaining tags, but allow <a> tags to remain for processing
    $textWithLinks = strip_tags($processedHtml);

    // 3. Use regular expressions to find and format the links

     $textWithLinks = str_replace('&nbsp;', '', $textWithLinks);


    // 4. Clean up any extra whitespace
    $smsText = trim($textWithLinks );

    return $smsText;
  }
}