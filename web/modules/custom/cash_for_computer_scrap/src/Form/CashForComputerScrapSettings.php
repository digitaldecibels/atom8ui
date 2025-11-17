<?php

namespace Drupal\cash_for_computer_scrap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\FieldOverride;

/**
 * Configure Module Name settings for this site.
 */
class CashForComputerScrapSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cash_for_computer_scrap_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['cash_for_computer_scrap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('cash_for_computer_scrap.settings');



    // --- New Design Settings Section ---
    $form['design_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Design & Branding'),
        '#open' => TRUE,
    ];

    $form['design_settings']['primary_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Primary Color'),
      '#description' => $this->t('Enter the hexadecimal code for the primary brand color (e.g., #007EE5).'),
      '#default_value' => $config->get('primary_color'),
    ];

    $form['design_settings']['secondary_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secondary Color'),
      '#description' => $this->t('Enter the hexadecimal code for the secondary brand color (e.g., #FFC107).'),
      '#default_value' => $config->get('secondary_color'),
    ];

    $form['design_settings']['general_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('General Background Color'),
      '#description' => $this->t('Enter the hexadecimal code for the general background or accent color.'),
      '#default_value' => $config->get('general_color'),
    ];

    // Logo (Managed File Field)
    $form['design_settings']['logo'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Business Logo'),
      '#description' => $this->t('Upload the business logo image. Max size: 1MB. Allowed extensions: png gif jpg jpeg.'),
      '#upload_location' => 'public://logos/', // Ensure this directory exists and is writable
      '#default_value' => $config->get('logo'),
      '#upload_validators' => [
        'file_validate_extensions' => ['png gif jpg jpeg'],
        'file_validate_size' => [1024 * 1024], // 1MB limit
      ],
      // Required to keep the file permanent after submission.
      '#managed_file_trigger' => 'submit',
    ];


    // --- New General Business Information Section ---
    $form['general_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('General Business Information'),
        '#open' => TRUE,
    ];

$form['general_settings']['business_address'] = [
  '#type' => 'address',
  '#title' => $this->t('Business Address'),
  '#description' => $this->t('The full street address for your business.'),
  '#default_value' => $config->get('business_address'),
  '#default_country' => 'US',
  '#available_countries' => ['US'],
  '#field_overrides' => [
     AddressField::ORGANIZATION => FieldOverride::REQUIRED,
    AddressField::ADDRESS_LINE3 => FieldOverride::HIDDEN,
    AddressField::FAMILY_NAME => FieldOverride::REQUIRED,
    AddressField::GIVEN_NAME => FieldOverride::REQUIRED,
  ],

];

    $form['general_settings']['business_phone'] = [
      '#type' => 'tel', // Use 'tel' for better mobile UX
      '#title' => $this->t('Business Phone Number'),
      '#description' => $this->t('The primary contact phone number (e.g., +1-555-123-4567).'),
      '#default_value' => $config->get('business_phone') ? $config->get('business_phone') : '',
    ];


       $form['general_settings']['drop_off_phone'] = [
      '#type' => 'tel', // Use 'tel' for better mobile UX
      '#title' => $this->t('Drop-Off Phone Number'),
      '#description' => $this->t('The phone number to call when dropping off lots'),
      '#default_value' => $config->get('drop_off_phone') ? $config->get('drop_off_phone') : '',
    ];

    // --- Existing Footer Fields ---

    $form['email_footer'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Email Footer'),
      '#description' => $this->t('Enter the full HTML text that should appear at the bottom of all system-generated emails.'),
      '#default_value' => $config->get('email_footer.value'),
      '#format' => $config->get('email_footer.format') ?? 'full_html',
    ];

    $form['sms_footer'] = [
      '#type' => 'text_format',
      '#title' => $this->t('SMS Footer'),
      '#description' => $this->t('Enter the full HTML text that should be appended to all system-generated SMS messages. Keep it concise.'),
      '#default_value' => $config->get('sms_footer.value'),
      '#format' => $config->get('sms_footer.format') ?? 'full_html',
    ];


      $form['packing_slip'] = [
      '#type' => 'text_format',
      '#title' => $this->t('Packing Slip Footer'),
      '#description' => $this->t('Enter the full HTML text that should appear at the bottom of packing slips.'),
      '#default_value' => $config->get('packing_slip.value'),
      '#format' => $config->get('packing_slip.format') ?? 'full_html',
    ];


    // --- Existing Twilio Settings ---

    $form['twilio_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('Twilio API Settings'),
        '#open' => TRUE,
    ];

    $form['twilio_settings']['twilio_sid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio Account SID'),
      '#description' => $this->t('Your Twilio Account SID, found on your Twilio Dashboard.'),
      '#default_value' => $config->get('twilio_sid'),
      '#required' => TRUE,
    ];

    $form['twilio_settings']['twilio_auth_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio Auth Token'),
      '#description' => $this->t('Your Twilio Auth Token, found on your Twilio Dashboard.'),
      '#default_value' => $config->get('twilio_auth_token'),
      '#required' => TRUE,
    ];

    $form['twilio_settings']['twilio_from_number'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twilio "From" Number'),
      '#description' => $this->t('The Twilio phone number (e.g., +15017122661) used to send SMS messages.'),
      '#default_value' => $config->get('twilio_from_number'),
      '#required' => TRUE,
    ];



    // --- Fedex Settings ---

    $form['fedex_settings'] = [
        '#type' => 'details',
        '#title' => $this->t('FedEx API Settings'),
        '#open' => TRUE,
    ];

    $form['fedex_settings']['fedex_api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FedEx API Key'),
      '#description' => $this->t('Your FedEx API Key for your Fedex Project.'),
      '#default_value' => $config->get('fedex_api_key'),
      '#required' => TRUE,
    ];

    $form['fedex_settings']['fedex_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FedEx Secret Key'),
      '#description' => $this->t('Your FedEx secret key, found in your fedex project settings'),
      '#default_value' => $config->get('fedex_secret_key'),
      '#required' => TRUE,
    ];

    $form['fedex_settings']['fedex_shipping'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FedEx Shipping Account'),
      '#description' => $this->t('Your FedEx shipper account number, found in your fedex project settings'),
      '#default_value' => $config->get('fedex_shipping'),
      '#required' => TRUE,
    ];



    $form['fedex_settings']['fedex_billing'] = [
      '#type' => 'textfield',
      '#title' => $this->t('FedEx Billing'),
      '#description' => $this->t('Your FedEx billing account number, found in your fedex project settings'),
      '#default_value' => $config->get('fedex_billing'),
      '#required' => TRUE,
    ];



    return parent::buildForm($form, $form_state);
  }




   /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('cash_for_computer_scrap.settings');

    // Save Design Settings
    $config->set('primary_color', $form_state->getValue('primary_color'));
    $config->set('secondary_color', $form_state->getValue('secondary_color'));
    $config->set('general_color', $form_state->getValue('general_color'));

    // Save Logo (Managed File ID)
    $logo_fid = $form_state->getValue('logo');
    $config->set('logo', $logo_fid);

    // Make the file permanent if it was uploaded.
    if (!empty($logo_fid) && $file = \Drupal\file\Entity\File::load($logo_fid[0])) {
        $file->setPermanent();
        $file->save();
        // The config stores an array of FIDs, so save the first one.
        $config->set('logo', $logo_fid);
    }


    // Save General Settings
    $config->set('business_address', $form_state->getValue('business_address'));
    $config->set('business_phone', $form_state->getValue('business_phone'));
    $config->set('drop_off_phone', $form_state->getValue('drop_off_phone'));


    // Save Footer and Twilio Settings
    $config->set('email_footer', $form_state->getValue('email_footer'));
    $config->set('sms_footer', $form_state->getValue('sms_footer'));
    $config->set('packing_slip', $form_state->getValue('packing_slip'));


    // SaveTwilio Settings
    $config->set('twilio_sid', $form_state->getValue('twilio_sid'));
    $config->set('twilio_auth_token', $form_state->getValue('twilio_auth_token'));
    $config->set('twilio_from_number', $form_state->getValue('twilio_from_number'));


    // save Fedex settings
    $config->set('fedex_api_key', $form_state->getValue('fedex_api_key'));
    $config->set('fedex_secret_key', $form_state->getValue('fedex_secret_key'));
    $config->set('fedex_shipping', $form_state->getValue('fedex_shipping'));
    $config->set('fedex_billing', $form_state->getValue('fedex_billing'));


    $config->save();

    parent::submitForm($form, $form_state);
  }

}