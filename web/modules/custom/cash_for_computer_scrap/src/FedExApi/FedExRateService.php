<?php

namespace Drupal\cash_for_computer_scrap\FedExApi;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Core\Datetime\DrupalDateTime;
use TCPDF;

/**
 * Service to handle all FedEx Rates and Transit Times API calls.
 */
class FedExRateService {

  const API_AUTH_URL = 'https://apis-sandbox.fedex.com/oauth/token';
  const API_RATE_URL = 'https://apis-sandbox.fedex.com/rate/v1/rates/quotes';
  const API_SHIP_URL = 'https://apis-sandbox.fedex.com/ship/v1/shipments';
  const API_LOCATION_URL = 'https://apis-sandbox.fedex.com/location/v1/locations';

  protected ClientInterface $httpClient;
  protected ConfigFactoryInterface $configFactory;
  protected \Psr\Log\LoggerInterface $logger;

  /**
   * Constructs a new FedExRateService object.
   *
   * @param \GuzzleHttp\ClientInterface $http_client
   * The Guzzle HTTP client.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   * The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   * The logger factory.
   */


  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->logger = $logger_factory->get('cash_for_computer_scrap_fedex');
  }

  /**
   * Retrieves an OAuth 2.0 access token for API authorization.
   *
   * @return string|null
   * The access token string, or NULL on failure.
   */
  protected function getAccessToken(): ?string {
    $config = $this->configFactory->get('cash_for_computer_scrap.settings');
    $client_id = $config->get('fedex_api_key');
    $client_secret = $config->get('fedex_secret_key');

    // 1. In a production scenario, you would first check a cache/state
    // for a valid, unexpired token before making this request.

    if (empty($client_id) || empty($client_secret)) {
      $this->logger->error('FedEx API Client ID or Secret is not configured.');
      return NULL;
    }

    try {
      $response = $this->httpClient->post(self::API_AUTH_URL, [
        'headers' => ['Content-Type' => 'application/x-www-form-urlencoded'],
        'form_params' => [
          'grant_type' => 'client_credentials',
          'client_id' => $client_id,
          'client_secret' => $client_secret,
        ],
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (isset($data['access_token'])) {
        // 2. The token and expiry time should be saved here.
        return $data['access_token'];
      }

    } catch (RequestException $e) {
      $this->logger->error('FedEx OAuth Error: @message', ['@message' => $e->getMessage()]);
    }
    return NULL;
  }




  /**
   * Requests shipping rates from the FedEx API.
   *
   * @param array $shipment_details
   * An array of shipment data (origin, destination, packages, etc.).
   * @return array
   * An array of available rates, or an empty array on failure.
   */


  public function getRates(array $shipment_details): array {

    $token = $this->getAccessToken();

    if (!$token) {
      return []; // Failed to authenticate
    }

    $payload = $this->buildRateRequestPayload($shipment_details);


    // TEMPORARY DEBUGGING CODE: Use var_export() for clear data types.
    // $payload_dump = var_export($payload, TRUE);
    // file_put_contents('sites/default/files/fedex_payload_dump.txt', $payload_dump);


    try {
      $response = $this->httpClient->post(self::API_RATE_URL, [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type' => 'application/json',
        ],
        'json' => $payload,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);


      return $this->parseRateResponse($data);
} catch (RequestException $e) {

      $full_error_message = 'An unknown error occurred.';
      $error_details = '';

      // Check if a response object is available (it should be for a 422 error).
      if ($e->hasResponse()) {
        // 1. Get the full, non-truncated response body.
        $response_body = $e->getResponse()->getBody()->getContents();

        // 2. Log the raw response for debugging purposes.
        $this->logger->error('FedEx API Full Response: @body', ['@body' => $response_body]);

        // 3. Attempt to decode the JSON to extract the human-readable error.
        $data = json_decode($response_body, TRUE);

        // Check for common FedEx/API error structures.
        if (isset($data['errors']) && is_array($data['errors'])) {
            // Use the code and message from the first error in the list.
            $error = reset($data['errors']);
            $error_details = ': ' . $error['code'] . ' - ' . $error['message'];
        }

        // Use the HTTP status code and the extracted details.
        $full_error_message = 'FedEx Rate Request Error (' . $e->getResponse()->getStatusCode() . ')';
        $full_error_message .= $error_details;

      } else {
        // Fallback for non-HTTP errors (e.g., connection failure).
        $full_error_message = $e->getMessage();
      }

      // Log the full message for debugging.
      $this->logger->error($full_error_message);

      // Display the full error to the user (if appropriate).
      \Drupal::messenger()->addError($full_error_message);
    }
    return [];
  }



  /**
   * Builds the JSON payload for the FedEx Rate API request.
   *
   * NOTE: The full payload structure is complex and must perfectly match
   * FedEx's documentation. This is a simplified example.
   *
   * @param array $details
   * Shipment details array.
   * @return array
   * The API request payload.
   */


  protected function buildRateRequestPayload(array $details): array {
    $config = $this->configFactory->get('cash_for_computer_scrap.settings');
    $account_number = $config->get('fedex_shipping');

    return [
      'accountNumber' => ['value' => $account_number],
      'rateRequestControlParameters' => [
        'returnTransitTimes' => TRUE,
      ],
      'requestedShipment' => [
        // Shipper/Origin Address
        'shipper' => [
          'address' => [
            'postalCode' => $details['origin_zip'],
            'countryCode' => $details['origin_country'],
          ],
        ],
        // Recipient/Destination Address
        'recipient' => [
          'address' => [
            'postalCode' => $details['destination_zip'],
            'countryCode' => $details['destination_country'],
            'residential' => $details['is_residential'],
          ],
        ],

        // type
        'pickupType' => $details['pickup_type'],

        // Date
       'shipDateStamp' => date('Y-m-d', strtotime('+1 day')),

        // declared value
        'totalDeclaredValue' => [
          'amount' => 0.0,
          'currency' => 'USD',
        ],

        // Package details
        'requestedPackageLineItems' => $details['packages'],
        'serviceType' => "",
        'packagingType' => 'YOUR_PACKAGING',
        'shippingChargesPayment' => [
          'paymentType' => 'RECIPIENT',
        ],
        'preferredCurrency' => 'USD',
        'rateRequestType' => ['LIST', 'ACCOUNT'], // Request both retail and negotiated rates
      ],
    ];
  }

  /**
   * Parses the raw FedEx API response into a simplified Drupal rates array.
   *
   * @param array $response_data
   * The decoded JSON response from the FedEx API.
   * @return array
   * An array of simplified rate objects.
   */
  protected function parseRateResponse(array $response_data): array {
    $rates = [];

    // Check for successful response structure.
    if (!isset($response_data['output']['rateReplyDetails'])) {
        $this->logger->warning('FedEx API response missing rate details: @data', ['@data' => print_r($response_data)]);
        return [];
    }

    foreach ($response_data['output']['rateReplyDetails'] as $rate_detail) {

    $delivery_date = $rate_detail['commit']['dateDetail']['dayFormat'];
    $date_object = new DrupalDateTime($delivery_date , 'UTC');
    $formatted_date = $date_object->format('m-d-Y');

      // Ensure we have rated shipment details and a net charge.
      if (isset($rate_detail['ratedShipmentDetails'][0]['totalNetCharge'])) {
        $rates[] = [
          // The service type (e.g., PRIORITY_OVERNIGHT)
          'service_code' => $rate_detail['serviceType'],
          // The actual shipping price
          'price' => $rate_detail['ratedShipmentDetails'][0]['totalNetCharge'],
          // The estimated transit time/date
          'delivery_date' => $formatted_date ?? 'N/A',
        ];
      }
    }

    return $rates;
  }




  /**
 * Calls the FedEx Ship API to create a shipping label.
 *
 * @param array $shipment_details
 * The detailed shipment and selected rate data.
 * @return array|null
 * An array containing tracking number and label data, or NULL on failure.
 */
public function createLabel(array $shipment_details): ?array {
  $token = $this->getAccessToken();

  if (!$token) {
    return NULL; // Failed to authenticate
  }

  $payload = $this->buildShipRequestPayload($shipment_details);

  try {
    $response = $this->httpClient->post(self::API_SHIP_URL, [
      'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
      ],
      'json' => $payload,
    ]);

    $data = json_decode($response->getBody()->getContents(), TRUE);

    // Check if we have package responses
    if (isset($data['output']['transactionShipments'][0]['pieceResponses'])) {
      $piece_responses = $data['output']['transactionShipments'][0]['pieceResponses'];

      // Create the directory if it doesn't exist
      $directory = 'private://fedex_labels';
      \Drupal::service('file_system')->prepareDirectory(
        $directory,
        \Drupal\Core\File\FileSystemInterface::CREATE_DIRECTORY | \Drupal\Core\File\FileSystemInterface::MODIFY_PERMISSIONS
      );

      // Create new merged PDF using FPDI (extends TCPDF)
      $merged_pdf = new \setasign\Fpdi\Tcpdf\Fpdi();
      $merged_pdf->setPrintHeader(false);
      $merged_pdf->setPrintFooter(false);
      $merged_pdf->SetMargins(0, 0, 0);
      $merged_pdf->SetAutoPageBreak(false, 0);

      $tracking_numbers = [];

      // Loop through each package and add to merged PDF
      foreach ($piece_responses as $index => $piece) {
        if (isset($piece['packageDocuments'][0]['encodedLabel'])) {
          $encoded_label = $piece['packageDocuments'][0]['encodedLabel'];
          $pdf_content = base64_decode($encoded_label);

          // Save to temporary file
          $temp_file = \Drupal::service('file_system')->tempnam('temporary://', 'fedex_');
          file_put_contents($temp_file, $pdf_content);

          // Import the PDF pages
          $pageCount = $merged_pdf->setSourceFile($temp_file);
          for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $merged_pdf->AddPage();
            $tplIdx = $merged_pdf->importPage($pageNo);
            $merged_pdf->useImportedPage($tplIdx, 0, 0);
          }

          // Clean up temp file
          unlink($temp_file);

          // Store tracking number
          if (isset($piece['trackingNumber'])) {
            $tracking_numbers[] = $piece['trackingNumber'];
          }
        }
      }

      // Save the merged PDF
      $merged_content = $merged_pdf->Output('', 'S');
      $filename = 'fedex_label_' . time() . '.pdf';

      $file = \Drupal::service('file.repository')->writeData(
        $merged_content,
        $directory . '/' . $filename,
        \Drupal\Core\File\FileSystemInterface::EXISTS_REPLACE
      );

      if ($file) {
        $this->logger->notice('Merged FedEx label saved: @filename with @count packages', [
          '@filename' => $filename,
          '@count' => count($piece_responses),
        ]);

        return [
          'file' => $file,
          'tracking_numbers' => $tracking_numbers,
          'package_count' => count($piece_responses),
          'file_url' => \Drupal::service('file_url_generator')->generateAbsoluteString($file->getFileUri()),
        ];
      } else {
        $this->logger->error('Failed to save merged FedEx label');
        return NULL;
      }
    }

    // If successful but missing key data, log the full response
    $this->logger->warning('FedEx Ship API missing key data: @data', ['@data' => print_r($data, TRUE)]);
    return NULL;

  } catch (RequestException $e) {
    // Get the full response body from the exception, if available
    $response_body = $e->getResponse() ? $e->getResponse()->getBody()->getContents() : '';
    $error_message = $e->getMessage();

    // Attempt to decode the JSON error response from FedEx
    $error_data = json_decode($response_body, TRUE);

    $user_facing_error = 'An unknown error occurred during label creation.';

    if ($error_data && !empty($error_data['errors'])) {
      // Extract the detailed, human-readable error from the FedEx JSON structure
      $detailed_errors = [];
      foreach ($error_data['errors'] as $error) {
        $detailed_errors[] = ($error['code'] ?? 'API_ERROR') . ': ' . ($error['message'] ?? 'No message provided.');
      }
      $user_facing_error = implode(' | ', $detailed_errors);
    } elseif ($response_body) {
      // Fallback: If it's not the standard JSON, but we have a body, use the body
      $user_facing_error = $response_body;
    }

    // Log the full detailed error for debugging
    $this->logger->error('FedEx Ship Request Failed: @message. Full Response: @body', [
      '@message' => $error_message,
      '@body' => $response_body,
    ]);

    // Display the clear, consolidated error message to the user
    \Drupal::messenger()->addError('FedEx Label Creation Error: ' . $user_facing_error);

    return NULL;
  }
}






  /**
   * Builds the complex JSON payload for the FedEx Ship API request.
   *
   * @param array $details
   * Detailed shipment data including the selected service.
   * @return array
   * The API request payload.
   */
  protected function buildShipRequestPayload(array $details): array {
    $config = $this->configFactory->get('cash_for_computer_scrap.settings');
    $account_number = $config->get('fedex_shipping');
    $billing_number = $config->get('fedex_billing');
    $cfcs_address = $config->get('business_address');

    // PAYLOAD

    $payload = [

    'accountNumber' => [
        'value' => $account_number, // Your main FedEx Account Number
    ],
    // 'labelResponseOptions' is often included outside of 'requestedShipment'
    // to specify the output format, as you had it.
    'labelResponseOptions' => 'LABEL',

    'requestedShipment' => [

        // 1. SHIPPER (Origin)
        'shipper' => [
            'contact' => [
                'personName' => $details['shipper_name'] ?? 'Origin Contact',
                'phoneNumber' => $details['shipper_phone'] ?? '1234567890',
                'companyName' => $details['shipper_company'] ?? 'Origin Company', // Added back
            ],
            'address' => [
                'streetLines' => [$details['origin_street'] ?? '939 E Parkway S'],
                'city' => $details['origin_city'] ?? 'Memphis',
                'stateOrProvinceCode' => $details['origin_state'] ?? 'TN',
                'postalCode' => $details['origin_zip'],
                'countryCode' => $details['origin_country'],
            ],
        ],


        'recipients' => [
          0 => [
        'contact' => [
            'personName' => $details['recipient_name'] ?? $cfcs_address['given_name'] . ' ' . $cfcs_address['family_name'],
            'phoneNumber' => $details['recipient_phone'] ?? str_replace('-', '', $config->get('business_phone')),
            'companyName' => $details['recipient_company'] ?? 'Destination Company',
        ],
        'address' => [
            'streetLines' => [$details['destination_street']],
            'city' => $details['destination_city'] ?? 'Columbus',
            'stateOrProvinceCode' => $details['destination_state'] ?? 'OH',
            'postalCode' => $details['destination_zip'],
            'countryCode' => $details['destination_country'],
        ],
    ],
],


        // 3. SERVICE DETAILS
        'serviceType' => $details['service_type'] ?? 'FEDEX_GROUND',
        'packagingType' => 'YOUR_PACKAGING',
        'pickupType' => $details['pickup_type'] ?? 'DROPOFF_AT_FEDEX_LOCATION', // Uses your value
        // 'shipDateStamp' is not required but is good practice to keep
        'shipDateStamp' =>  date('Y-m-d', strtotime('+1 day')),

        // 4. BILLING/PAYMENT
        'shippingChargesPayment' => [
              'paymentType' => 'SENDER',
            'payor' => [

                    'accountNumber' => [
                        'value' => $billing_number,
                    ],

                // Note: The original FedEx JSON included the payor's address here.
                // It's often redundant if the payor is the shipper, but including it
                // is safer based on the provided JSON:
                'address' => [
                    'streetLines' => ['2000 Freight LTL Testing'],
                    'city' =>  'Harrison',
                    'stateOrProvinceCode' => 'AR',
                    'postalCode' => '72601',
                    'countryCode' => 'US',
                ],
            ],
        ],


        // 5. pdf label details

        'labelSpecification' => array (
          'imageType' => 'PDF',
          // Change to a stock type widely supported for Express PDF labels
          'labelStockType' => 'PAPER_4X6',

          // You might also need this for Express:
          'labelFormatType' => 'COMMON2D',
        ),

        // 6. DECLARED VALUE (Insurance)
        // 'totalDeclaredValue' => [
        //     'amount' => (float) ($details['declared_value'] ?? 0.0),
        //     'currency' => 'USD',
        // ],

        // 'totalWeight' => array(
        //     'units' => 'LB',
        //     'value' =>  $details['total_weight'],
        // ),

        // 7. PACKAGE DETAILS
        // This takes your pre-built package array, which should contain weight and dimensions for each package.

        // 'packageCount' => $details['package_count'],

        'requestedPackageLineItems' => $details['packages'],
    ],
];




    $payload_dump = var_export($payload, TRUE);
    file_put_contents('sites/default/files/fedex_label_request_dump.txt', $payload_dump);

      return $payload;

  }

// Add this function to your FedExRateService class.

  /**
   * Finds the nearest FedEx location for package drop-off.
   *
   * @param string $postal_code
   * The postal code of the search area.
   * @param string $country_code
   * The country code of the search area (e.g., 'US').
   * @param float $radius_distance
   * The radius distance to search within (e.g., 5.0).
   * @param string $radius_unit
   * The unit of the radius ('KM' or 'MI').
   *
   * @return array
   * An array of nearest locations, or an empty array on failure.
   */

  public function findNearestDropoffLocation(string $city, string $state, string $postal_code, string $country_code = 'US', float $radius_distance = 5.0, string $radius_unit = 'MI'): array {

    $token = $this->getAccessToken();
    if (!$token) {
      return []; // Failed to authenticate
    }

    $payload = [
      'locationsSummaryRequestControlParameters' => [
        'distance' => [
          'units' => 'MI',
          'value' => 2,
        ],
      ],
      'locationSearchCriterion' => 'ADDRESS',
      'location' => [
        'address' => [
          'city' => $city,
          'stateOrProvinceCode' => $state,
          'postalCode' => $postal_code,
          'countryCode' => $country_code,
        ],
      ],
    ];



    try {
      $response = $this->httpClient->post(self::API_LOCATION_URL, [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type' => 'application/json',
        ],
        'json' => $payload,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      // Extract the location details from the response.
      if (isset($data['output']['locationDetailList'])) {
        return $this->parseLocationResponse($data['output']['locationDetailList']);
      }

      $this->logger->warning('FedEx Location API missing location details: @data', ['@data' => print_r($data, TRUE)]);
      return [];

    } catch (RequestException $e) {
      $this->logger->error('FedEx Location Search Error: @message', ['@message' => $e->getMessage()]);
      \Drupal::messenger()->addError('FedEx Location Search Error: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Parses the raw FedEx Location Search API response into a simplified array.
   *
   * @param array $location_data
   * The array of location details from the API response.
   * @return array
   * An array of simplified location objects.
   */
  protected function parseLocationResponse(array $location_data): array {

    $locations = [];
    foreach ($location_data as $detail) {
      $location_name = $detail['contactAndAddress']['contact']['companyName'] ?? 'FedEx Location';
      $address = $detail['contactAndAddress']['address'];

      $locations[] = [
        'name' => $location_name,
        'distance_miles' => $detail['distance']['value'] ?? 'N/A',
        'address' => implode(', ', $address['streetLines']),
        'city' => $address['city'],
        'state' => $address['stateOrProvinceCode'],
        'zip' => $address['postalCode'],
        'hours' => $detail['operatingHours']['serviceHours'] ?? 'N/A', // Further parsing of hours is often needed
        'location_type' => $detail['locationType'],
        'location_id' => $detail['locationId'],
      ];
    }
    return $locations;
  }


}
