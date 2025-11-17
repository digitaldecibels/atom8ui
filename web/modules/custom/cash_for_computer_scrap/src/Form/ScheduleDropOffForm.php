<?php

namespace Drupal\cash_for_computer_scrap\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Symfony\Component\DependencyInjection\ContainerInterface;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;




/**
 * Form for scheduling computer scrap lot pickups.
 */
class ScheduleDropOffForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new SchedulePickupForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, MessengerInterface $messenger) {
    $this->entityTypeManager = $entity_type_manager;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cash_for_computer_scrap_schedule_drop_off_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $nid = NULL) {



        // attach library
      $form['#attached']['library'][] = 'cash_for_computer_scrap/calendly';


            // Add a wrapper for the form to be replaced with new content after AJAX.
  $form['#prefix'] = '<div id="ajax-form-wrapper">';
  $form['#suffix'] = '</div>';


    // Get the lot node from the URL parameter
    $lot_node = NULL;
    if ($nid) {
      $node_storage = $this->entityTypeManager->getStorage('node');
      $lot_node = $node_storage->load($nid);
    }

    // Display lot information
    if ($lot_node && $lot_node->bundle() === 'lot') {
      $form['lot_info'] = [
        '#type' => 'item',
        '#title' => $this->t('Lot'),
        '#markup' => $this->t('<strong>@title</strong> (ID: @nid)', [
          '@title' => $lot_node->getTitle(),
          '@nid' => $lot_node->id(),
        ]),
      ];

      // Store the node ID as a hidden field
      $form['lot_node_id'] = [
        '#type' => 'hidden',
        '#value' => $nid,
      ];



    }
    else {
      // If no valid lot node, show error and autocomplete fallback
      if ($nid) {
        $this->messenger->addError($this->t('Invalid lot ID provided or lot does not exist.'));
      }

      $form['lot_node_id'] = [
        '#type' => 'entity_autocomplete',
        '#title' => $this->t('Lot'),
        '#description' => $this->t('Select a lot from the available options.'),
        '#target_type' => 'node',
        '#selection_settings' => [
          'target_bundles' => ['lot'],
        ],
        '#required' => TRUE,
        '#validate_reference' => TRUE,
      ];
    }


    $form['scheduled_id'] = [
      '#type' => 'textfield',
          '#attributes' => [
      'class' => ['visually-hidden'],
    ],
    ];

    $form['scheduled_date'] = [
      '#type' => 'textfield',
          '#attributes' => [
      'class' => ['visually-hidden'],
    ],
    ];



    $form['calendly'] = [
      '#markup' => '<div id="calendly-form" data-url="https://calendly.com/rick-digitaldecibels/30min?hide_event_type_details=1&hide_gdpr_banner=1" style="min-width:320px;height:1700px;"></div>',
      '#title' => $this->t('Calendly'),
      '#description' => $this->t('Optional notes about this lot processing.'),

    ];



  $form['actions'] = [
    '#type' => 'actions',
  ];

  $form['actions']['submit'] = [
    '#type' => 'submit',
    '#value' => $this->t('Submit via AJAX'),
    '#attributes' => [
      'class' => ['visually-hidden'],
    ],
    '#ajax' => [
      'callback' => '::ajaxSubmitCallback',
      'event' => 'click',
      'wrapper' => 'ajax-form-wrapper',
    ],
  ];



    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $lot_node_id = $form_state->getValue('lot_node_id');

    if (!empty($lot_node_id)) {
      // Validate that the selected node exists and is of type 'lot'.
      $node_storage = $this->entityTypeManager->getStorage('node');
      $node = $node_storage->load($lot_node_id);

      if (!$node) {
        $form_state->setErrorByName('lot_node_id', $this->t('The selected lot does not exist.'));
      }
      elseif ($node->bundle() !== 'lot') {
        $form_state->setErrorByName('lot_node_id', $this->t('The selected item is not a lot.'));
      }
    }


  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    $lot_node_id = $values['lot_node_id'];

    // Load the lot node to get its title for the message.
    $node_storage = $this->entityTypeManager->getStorage('node');
    $lot_node = $node_storage->load($lot_node_id);


    $lot_title = $lot_node ? $lot_node->getTitle() : 'Unknown';


    // The date string you get from your AJAX response.
    $ajax_date_string = $values['scheduled_date'];


    $date_time_object = new DrupalDateTime($ajax_date_string);

    // To store it in a Drupal date field, you can now format it.
    // The storage format for a 'datetime' field is typically 'Y-m-d\TH:i:s'.
    $formatted_date_for_field = $date_time_object->format('Y-m-d\TH:i:s');


    $lot_node->set('field_scheduled_date', $formatted_date_for_field);
    $lot_node->set('field_scheduled_id', $values['scheduled_id'] );

    // Set the new moderation state.
    $lot_node->set('moderation_state', 'drop_off_scheduled');
    $lot_node->save();



    // Here you would typically save this data to a custom entity, database table,
    // or update the lot node with this information.
    // For now, we'll just show a success message.

    $this->messenger->addMessage($this->t('Successfully submitted lot form for "@lot_title" scheduled for @datetime.', [
      '@lot_title' => $lot_title,
      '@datetime' => $formatted_datetime,
    ]));

    // Optionally log the submission or save to database here.
    \Drupal::logger('cash_for_computer_scrap')->info('Schedule pickup form submitted: Node ID @nid, DateTime @datetime, Notes: @notes', [
      '@nid' => $lot_node_id,
      '@datetime' => $formatted_datetime,
      '@notes' => $notes ?: 'None',
    ]);

    // Redirect after form submission.
    // $form_state->setRedirect('<front>');

      $this->messenger->addMessage($this->t('Fallback form submission successful.'));
  }



  /**
 * AJAX callback for the form submission.
 */
public function ajaxSubmitCallback(array &$form, FormStateInterface $form_state) {
  // Create an AjaxResponse object.
  $response = new AjaxResponse();

  // Grab the submitted values from the form state.
  $values = $form_state->getValues();

  // You can now access specific values like this:
  $lot_node_id = $values['lot_node_id'];
  $event_id = $values['event_id'];
  $event_date = $values['event_date'];

  // You can perform your form processing logic here, such as:
  // - Saving data to a custom entity.
  // - Updating a node.
  // - Sending an email.

  // For demonstration, let's build a message with the submitted values.
  $message = $this->t('Form submitted successfully! <br>Lot Node ID: @lot_node_id <br>Event ID: @event_id <br>Event Date: @event_date', [
    '@lot_node_id' => $lot_node_id,
    '@event_id' => $event_id,
    '@event_date' => $event_date,
  ]);

  // Use an Ajax command to display the message.
  // For example, you can replace the entire form with a success message.
  $response->addCommand(new HtmlCommand('#ajax-form-wrapper', $message));



  return $response;
}



}