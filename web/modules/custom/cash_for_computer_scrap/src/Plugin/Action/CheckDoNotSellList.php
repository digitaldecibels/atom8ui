<?php

namespace Drupal\cash_for_computer_scrap\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\eca\Plugin\Action\ConfigurableActionBase;
use Drupal\eca\Plugin\ECA\PluginFormTrait;
use Drupal\eca_content\Event\ContentEntityPrepareForm;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Datetime\DrupalDateTime;

/**
 * Check if a user is on the do not sell list.
 *
 * @Action(
 * id = "cash_for_computer_scrap_do_not_sell",
 * label = @Translation("Check do not sell list"),
 * description = @Translation("check do not sell list"),
 * eca_version_introduced = "2.1.0",
 * type = "entity"
 * )
 */
class CheckDoNotSellList extends ConfigurableActionBase {

  use PluginFormTrait;

  /**
   * The instantiated entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface|null
   */
  protected ?EntityInterface $entity;

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'token_name' => 'is_on_do_not_sell_list',
      'message' => 'The do not sell list has been checked'
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['token_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Token name'),
      '#default_value' => $this->configuration['token_name'],
      '#description' => $this->t('Provide the name of the token that will hold the result of the check. The token will be available in the workflow as `action.your_token_name`.'),
      '#weight' => -60,
    ];

    $form['message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Message'),
      '#default_value' => $this->configuration['message'],
      '#description' => $this->t('This provides the message to add to the revision log message`.'),
      '#weight' => -60,
    ];


    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->configuration['token_name'] = $form_state->getValue('token_name');
    $this->configuration['message'] = $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */

public function execute(mixed $entity = NULL): void {


    if ($entity && method_exists($entity, 'getOwner')) {
        $owner = $entity->getOwner();
        \Drupal::logger('cash_for_computer_scrap')->emergency('Owner ID: ' . ($owner ? $owner->id() : 'NULL'));
        \Drupal::logger('cash_for_computer_scrap')->emergency('Owner has field_first_name: ' . ($owner && $owner->hasField('field_first_name') ? 'YES' : 'NO'));
    } else {
        \Drupal::logger('cash_for_computer_scrap')->emergency('Entity has no getOwner method');
    }

    // Validate entity
    if (!($entity instanceof ContentEntityInterface)) {
      \Drupal::logger('cash_for_computer_scrap')->warning('Invalid entity provided to CheckDoNotSellList action');

      $this->tokenService->addTokenData($this->configuration['token_name'], 'no');
          $this->tokenService->addTokenData($this->configuration['message'], 'Invalid entity provided to CheckDoNotSellList action');
      return;
    }

    // Get user data
    $author = $entity->getOwner();


    $first_name = $author->field_first_name[0]->value ?? '';
    $last_name = $author->field_last_name[0]->value ?? '';
    $dob = $author->field_dob[0]->value ?? '';
    $dl_number = $author->field_drivers_license_number[0]->value ?? '';
    $dl_state = $author->field_drivers_license_state[0]->value ?? '';

    // Set the default timezone to avoid warnings
date_default_timezone_set('America/New_York');

// Get the current date and time formatted as "Wednesday, July 4th, at 4:52pm"
$formattedDate = date('l, F jS, \a\t g:ia');



// Get User IP Address :

$ip = '';

  if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP address from a shared internet connection
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP address from a proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        // IP address from the remote address
        $ip = $_SERVER['REMOTE_ADDR'];
    }




    $log_messages = [];

    // Validate required fields
    if (empty($first_name) || empty($last_name)) {
      $message = "Missing first name or last name for user ID {$author->id()}";
      \Drupal::logger('cash_for_computer_scrap')->warning($message);
      $log_messages[] = $message;
      \Drupal::messenger()->addError($message);

      $this->tokenService->addTokenData($this->configuration['token_name'], 'no');
       $this->tokenService->addTokenData($this->configuration['message'], 'Missing first name or last name for user ID ' .$author->id());

      return;
    }

    if (empty($dob)) {
      $message = "Missing date of birth for user ID {$author->id()}";
      \Drupal::logger('cash_for_computer_scrap')->warning($message);
      $log_messages[] = $message;
      \Drupal::messenger()->addError($message);


      $this->tokenService->addTokenData($this->configuration['token_name'], 'no');
      $this->tokenService->addTokenData($this->configuration['message'], 'Missing date of birth for user ID' . $author->id());


      return;
    }

    // Format date of birth
    try {
      $dob_object = new DrupalDateTime($dob);
      $formatted_dob = $dob_object->format("Y-m-d");
    } catch (\Exception $e) {
      $message = "Invalid date of birth format for user ID {$author->id()}: " . $e->getMessage();
      \Drupal::logger('cash_for_computer_scrap')->error($message);
      $log_messages[] = $message;
      \Drupal::messenger()->addError($message);


      $this->tokenService->addTokenData($this->configuration['token_name'], 'no');
      $this->tokenService->addTokenData($this->configuration['message'], 'Invalid date of birth format for user ID ' .$author->id() . ' ' . $e->getMessage());
      return;
    }

    // API credentials
    $username = 'Mario@Secure-Recycling.com';
    $password = '75937d776a017ef2';
    $facilityId = 'SMBC-2013-0000367';

    // Make API call
    try {
      $client = \Drupal::httpClient();
      $response = $client->post('https://services.dps.ohio.gov/ScrapDealerServices.API/v1/DoNotBuy/Search', [
        'headers' => [
          'Accept' => 'application/json',
          'username' => $username,
          'password' => $password,
        ],
        'json' => [
          'FacilityRegNumber' => $facilityId,
          'FirstName' => $first_name,
          'LastName' => $last_name,
          'DOB' => $formatted_dob,
          'DLNumber' => $dl_number,
          'DLState' => $dl_state,
        ],
        'timeout' => 30,
      ]);

      $status = $response->getStatusCode();
      $body = $response->getBody()->getContents();
      $data = json_decode($body, TRUE);

      \Drupal::logger('cash_for_computer_scrap')->info('API Response Status: ' . $status);
      \Drupal::logger('cash_for_computer_scrap')->info('API Response Data: ' . print_r($data, TRUE));

      // Determine if person is on the list
      if (is_array($data) && count($data) > 0) {
        $on_list = 'yes';
        $status_text = 'FOUND';
      } else {
        $on_list = 'no';
        $status_text = 'NOT FOUND';
      }



      $log_message = "$first_name $last_name was $status_text on the Do Not Sell list. IP Address:" . $ip . ". " . $formattedDate ;
      \Drupal::logger('cash_for_computer_scrap')->info($log_message);
      $log_messages[] = $log_message;

      $this->tokenService->addTokenData('message', $log_message);




    }
    catch (RequestException $e) {
      $error_message = "API request failed for $first_name $last_name: " . $e->getMessage();
      \Drupal::logger('cash_for_computer_scrap')->error($error_message);
      $log_messages[] = $error_message;
      \Drupal::messenger()->addError($error_message);
      $on_list = 'no'; // Default to 'no' on API failure
    }
    catch (\Exception $e) {
      $error_message = "Unexpected error for $first_name $last_name: " . $e->getMessage();
      \Drupal::logger('cash_for_computer_scrap')->error($error_message);
      $log_messages[] = $error_message;
      \Drupal::messenger()->addError($error_message);
      $on_list = 'no'; // Default to 'no' on unexpected error
    }



      // IMPORTANT: Set tokens AFTER the API call, outside try-catch
    $this->tokenService->addTokenData($this->configuration['token_name'], $on_list);
    $this->tokenService->addTokenData($this->configuration['message'], $log_message);



    // Display messages to user
    if (!empty($log_messages)) {
      \Drupal::messenger()->addStatus(implode('<br>', $log_messages));
    }


  }
}