<?php

namespace Drupal\cash_for_computer_scrap\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\cash_for_computer_scrap\FedExApi\FedExRateService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;



// shipping types:

// FedEx Express Saver®

/**
 * Custom form to display FedEx shipping rates for a node.
 */
class FedExRateForm extends FormBase implements ContainerInjectionInterface {


protected FedExRateService $fedexService;

protected ConfigFactoryInterface $configFactoryService;

  // 1. RE-ADD THE CONSTRUCTOR ⬇️
  /**
   * Constructs a new FedExRateForm.
   *
   * @param \Drupal\mymodule\FedExApi\FedExRateService $fedex_service
   * The custom FedEx Rate API service.
   */
  public function __construct(FedExRateService $fedex_service, ConfigFactoryInterface $config_factory) {
    $this->fedexService = $fedex_service;
    $this->configFactoryService = $config_factory;
  }

  // 2. RE-ADD THE STATIC CREATE METHOD ⬇️
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    // Inject our custom service here.
    return new static(
      $container->get('cash_for_computer_scrap.fedex_api'),
      $container->get('config.factory')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cash_for_computer_scrap_fedex_rate_form';
  }

  /**
   * {@inheritdoc}
   * The $node argument is automatically injected from the route /{node}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {


    // $form['#prefix'] = '<div id="fedex-rates-wrapper">';


    $city = $node->uid->entity->field_address[0]->locality;
    $state = $node->uid->entity->field_address[0]->administrative_area;
    $zip = $node->uid->entity->field_address[0]->postal_code;
    $country_code = $node->uid->entity->field_address[0]->country_code;

    //   // Call the external service to get rates.



    //   if (!$form_state->has('fedex_rates')) {
    // // Call the external service to get rates (SLOW OPERATION).
    // $rates = $this->getShippingRates($zip, $node);

    // // Store the rates in form state's temp storage.
    // $form_state->setStorage(['fedex_rates' => $rates]);

    // } else {
    //     // Rates are already stored, retrieve them instantly (FAST).
    //     $rates = $form_state->getStorage()['fedex_rates'];
    //   }


    //   // Build the render array for the rates using the helper function.
    //   $rates_markup = $this->buildRatesMarkup($rates, $zip);

    //   $form['rates'] = [
    //     "#type" => 'radios',
    //     "#options" =>  $rates_markup
    //   ];


    // $form['#suffix'] = '</div>';


    // 1. ADD THE AJAX BUTTON
    $form['hello_button'] = [
      '#type' => 'button',
      '#value' => $this->t('Say Hello!'),
      '#ajax' => [
        'callback' => '::helloAjaxCallback', // The method to call when clicked.
        'event' => 'click',
        'wrapper' => 'hello-message-wrapper', // The ID of the container to update.
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Saying hello...'),
        ],
      ],
      '#attributes' => [
        // A class to target the button if needed by CSS/JS.
        'class' => ['js-say-hello-button'],
      ],
    ];

    // 2. ADD THE MESSAGE CONTAINER
    // This is the container that will be replaced/updated by the AJAX callback.
    $form['hello_message_container'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'hello-message-wrapper'],
      // Initially, it can be empty or have a placeholder message.
      '#markup' => $this->t('<p>Click the button above to see a message.</p>'),
    ];



    $label = $this->getShippingLabel($node);

    dpm($label);







    return $form;


  }

// 3. DEFINE THE AJAX CALLBACK METHOD
  /**
   * AJAX callback for the "Say Hello!" button.
   *
   * @param array $form
   * The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * The current state of the form.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   * An AJAX response object.
   */public function helloAjaxCallback(array &$form, FormStateInterface $form_state) {
  $response = new AjaxResponse();




    $message = $this->t('<p style="color: green; font-weight: bold;">Hello! We found @count rates...</p>', [
      '@count' => $rate_count,
    ]);


  $response->addCommand(new HtmlCommand('#hello-message-wrapper', $message));

  return $response;
}





  /**
   * Helper function to call the service and map node data.
   *
   * @param string $destination_zip
   * The ZIP code entered by the user.
   * @param \Drupal\node\NodeInterface $node // <-- ADD $node parameter
   * The node entity from the route.
   * @return array
   * The array of rates from the FedEx service.
   */
  protected function getShippingRates(string $destination_zip, NodeInterface $node): array {

  $config = $this->configFactoryService->get('cash_for_computer_scrap.settings');
  $cfcs_address = $config->get('business_address');



    $cfcs_address = $config->get('business_address');

 $packages = array();

 foreach($node->field_packages as $package){

  $package = $package->entity;


 $packages[] = [
            'weight' => [
              'units' => 'LB', // LBS or KG
              'value' => (float) $package->field_weight[0]->value,
            ],

            'dimensions' => [
              'units' => 'IN', // LBS or KG
              'length' => (float) $package->field_length[0]->value,
              'width' => (float) $package->field_width[0]->value,
              'height' => (float) $package->field_height[0]->value,
            ],
          ];


 }


      //  Location Type
    $location_type = $node->uid->entity->field_location_type[0]->value;

    $residential = FALSE;
      if($location_type == 'residential'){
      $residential  = TRUE;
    }


    // Pickup Type
    $pickup_type = 'DROPOFF_AT_FEDEX_LOCATION';
    if($node->field_fedex_location[0]->value == 'pick_up_at_my_location'){
        $pickup_type = 'USE_SCHEDULED_PICKUP';
    }

    $user_address = $node->uid->entity->field_address[0];

    $details = [



// Shipper (customer)
'shipper_name'  => $node->uid->entity->field_first_name->value . ' ' . $node->uid->entity->field_last_name->value,
'shipper_phone' =>  $node->uid->entity->field_phone_number->value,
'shipper_company' => $node->uid->entity->field_address[0]->organization,
'origin_street' =>  $node->uid->entity->field_address[0]->address_line1,
'origin_city' => $node->uid->entity->field_address[0]->locality,
'origin_state' => $node->uid->entity->field_address[0]->administrative_area ,
'origin_zip' =>  $node->uid->entity->field_address[0]->postal_code,
'origin_country' =>  $node->uid->entity->field_address[0]->country_code,


// Company (Mario )
// 'recipient_name' => ,
'recipient_phone' => $config->get('business_phone'),
'recipient_company' => '',
'destination_street' => '',
'destination_city' => '',
'destination_state' => '',
'destination_zip' => ''  ,
'destination_country' => '',



      'is_residential' => $residential,
      'weight_unit' => 'LBS',
      'packages' => $packages,
      'pickup_type' => $pickup_type,
    ];




    // 2. Call the service.
    return $this->fedexService->getRates($details);
  }

  /**
   * Helper function to render the rates list.
   */
  protected function buildRatesMarkup(array $rates, string $zip)  {


    if (empty($rates)) {
      return $this->t('<p class="error">No shipping rates found for @zip.</p>', ['@zip' => $zip]);
    }


    foreach ($rates as $rate) {


      $price = number_format($rate['price'], 2);
      $list[$rate['service_code']] = $rate['service_code'] . '$' .  $price .' (Delivery by: ' . $rate['delivery_date'] . ')';

    }



    return $list;
  }

  /**
   * {@inheritdoc}
   *
   * This is a display-only form using AJAX for updates, so no heavy submit logic is needed.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Since the AJAX callback handles the display, we don't typically
    // need submit logic unless you are logging the rate check.
  }



  // Get nearest locations for dropdopp


  /**
   * Helper function to get closest dropoff locations
   *
   * @param string $destination_zip
   * The ZIP code entered by the user.
   * @param \Drupal\node\NodeInterface $node // <-- ADD $node parameter
   * The node entity from the route.
   * @return array
   * The array of rates from the FedEx service.
   */
  protected function getDropoffLocations($city, $statae, $zip, $country_code) {


    return $this->fedexService->findNearestDropoffLocation($city, $statae, $zip, $country_code);
  }









  /**
   * Helper function to call the service and generate shipping label.
   *

   * @param \Drupal\node\NodeInterface $node //
   * The node entity from the route.
   * @return array
   * The array of rates from the FedEx service.
   */
  protected function getShippingLabel( NodeInterface $node): array {

  $config = $this->configFactoryService->get('cash_for_computer_scrap.settings');
  $cfcs_address = $config->get('business_address');

 $packages = array();

 $total_weight = 0;

 $package_count = 0;



 foreach($node->field_packages as $key => $package){

  $package = $package->entity;

  $total_weight = $total_weight + (float) $package->field_weight[0]->value;
  $package_count++;



 $packages[] = [

  //  'sequenceNumber' => $key + 1,
            'weight' => [
              'units' => 'LB', // LBS or KG
              'value' => (float) $package->field_weight[0]->value,
            ],

            'dimensions' => [
              'units' => 'IN', // LBS or KG
              'length' => (float) $package->field_length[0]->value,
              'width' => (float) $package->field_width[0]->value,
              'height' => (float) $package->field_height[0]->value,
            ],
          ];
 }


      //  Location Type
    $location_type = $node->uid->entity->field_location_type[0]->value;

    // Pickup Type
    $pickup_type = 'DROPOFF_AT_FEDEX_LOCATION';
    if($node->field_fedex_location[0]->value == 'pick_up_at_my_location'){
        $pickup_type = 'USE_SCHEDULED_PICKUP';
    }

$user_address = $node->uid->entity->field_address[0];

$details = [
// Shipper (customer)
'shipper_name'  => $node->uid->entity->field_first_name->value . ' ' . $node->uid->entity->field_last_name->value,
'shipper_phone' =>  $node->uid->entity->field_phone->value,
'shipper_company' => $node->uid->entity->field_address[0]->organization,
'origin_street' =>  $node->uid->entity->field_address[0]->address_line1,
'origin_city' => $node->uid->entity->field_address[0]->locality,
'origin_state' => $node->uid->entity->field_address[0]->administrative_area ,
'origin_zip' =>  $node->uid->entity->field_address[0]->postal_code,
'origin_country' =>  $node->uid->entity->field_address[0]->country_code,


// Company (Mario )
'recipient_name' => $cfcs_address['given_name'] . ' ' . $cfcs_address['family_name'],
'recipient_phone' => str_replace('-', '', $config->get('business_phone')),
'recipient_company' => $cfcs_address['organization'],
'destination_street' => $cfcs_address['address_line1'],
'destination_city' => $cfcs_address['locality'],
'destination_state' => $cfcs_address['administrative_area'],
'destination_zip' => $cfcs_address['postal_code'] ,
'destination_country' => $cfcs_address['country_code'],


// Package Stuff
'servce_type' => 'FEDEX_GROUND',
'weight_unit' => 'LBS',
'packages' => $packages,
'pickup_type' => $pickup_type,

'total_weight' => $total_weight,
'package_count' => $package_count,


];




dpm($this->fedexService->createLabel($details));

return array('hello');


    return $this->fedexService->createLabel($details);


  }


}