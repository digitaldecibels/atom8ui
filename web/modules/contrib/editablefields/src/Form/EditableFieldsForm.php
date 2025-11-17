<?php

namespace Drupal\editablefields\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EditableFieldsForm.
 */
class EditableFieldsForm extends FormBase implements BaseFormIdInterface {

  /**
   * Entity updated in the form.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * Field name.
   *
   * @var string
   */
  protected $field_name;

  /**
   * Form mode.
   *
   * @var string
   */
  protected $form_mode;

  /**
   * Formatter settings.
   *
   * @var array $settings
   */
  protected $settings;

  /**
   * Drupal\editablefields\services\EditableFieldsHelper definition.
   *
   * @var \Drupal\editablefields\services\EditableFieldsHelper
   */
  protected $editablefieldsHelper;

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal\Core\Routing\CurrentRouteMatch instance.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->editablefieldsHelper = $container
      ->get('editablefields.helper');
    $instance->entityTypeManager = $container->get('entity_type.manager');
    $instance->routeMatch = $container->get('current_route_match');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return $this->getBaseFormId() . '_' . $this->prepareUniqueFormId();
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'editablefields_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $entity = $this->entity;
    // Get fresh entity.
    $entity = $this->entityTypeManager
      ->getStorage($entity->getEntityTypeId())
      ->load($entity->id());
    $wrapper = str_replace('_', '-', $this->getFormId()) . '-wrapper';
    $fallback = $this->settings['fallback_edit'];
    $form['#prefix'] = "<div id=\"$wrapper\">";
    $form['#suffix'] = '</div>';

    // Set entity language as a hidden field.
    $form['entity_language'] = [
      '#type' => 'hidden',
      '#value' => $this->entity->language()
        ? $this->entity->language()->getId()
        : NULL,
    ];

    $operation = $form_state->get('operation');

    $field = $this->field_name;
    $form_display = $this->getFormDisplay();
    $is_admin = $this->editablefieldsHelper->isAdmin();

    if ($form_display === NULL || !$form_display->id()) {
      if ($is_admin) {
        return [
          '#markup' => $this->t('Form mode @mode missing', [
            '@mode' => $this->form_mode,
          ]),
        ];
      }
      return [];
    }

    // If fallback formatter selected.
    if ($fallback && (!$operation || $operation === 'cancel')) {
      /** @var FieldItemListInterface $item */
      $item = $entity->get($field);
      $form['formatter'] = $item->view($this->settings['display_mode_edit']);
      if (empty($form['formatter'])) {
        $form['formatter'] = [
          '#markup' => $this->t('N/A'),
        ];
      }
      $form['formatter']['#weight'] = 0;
      $form['edit'] = [
        '#type' => 'submit',
        '#op' => 'edit',
        '#value' => $this->t('Edit'),
        '#weight' => 10,
        '#ajax' => [
          'callback' => [$this, 'ajaxCallback'],
          'wrapper' => $wrapper,
          'disable-refocus' => TRUE,
        ],
      ];

      return $form;
    }

    // Get the field widget from the form mode.
    $component = $form_display->getComponent($field);
    if (!$component) {
      if ($is_admin) {
        return [
          '#markup' => $this->t('The field @field is missing in the @mode', [
            '@field' => $field,
            '@mode' => $this->form_mode,
          ]),
        ];
      }
      return [];
    }

    // Add #parents to avoid error in WidgetBase::form.
    $form['#parents'] = [];

    // Get widget and prepare values for it.
    $widget = $form_display->getRenderer($field);
    if (is_null($widget)) {
      return [];
    }

    $items = $entity->get($field);
    $items->filterEmptyItems();

    // Get a widget form.
    $form[$field] = $widget->form($items, $form, $form_state);
    $form[$field]['#access'] = $items->access('edit');

    $form['submit'] = [
      '#type' => 'submit',
      '#op' => 'save',
      '#value' => $this->t('Update'),
      '#ajax' => [
        'callback' => [$this, 'ajaxCallback'],
        'wrapper' => $wrapper,
        'disable-refocus' => TRUE,
      ],
    ];

    if (!empty($this->settings['fields_ajax_trigger'])) {
      $form['submit']['#attributes']['class'][] = 'visually-hidden';
      $fields_ajax = explode(',', $this->settings['fields_ajax_trigger']);
      foreach ($fields_ajax as $field_ajax) {
        $event = $this->settings['fields_ajax_trigger_event'] ?: 'change';
        $event = 'on' . $event;
        $js = 'document.querySelector("#' . $wrapper . ' [type=submit]").dispatchEvent(new Event("mousedown"));';

        $form[$field_ajax]['widget']['#attributes'][$event] = $js;
        foreach (Element::children($form[$field_ajax]['widget']) as $subelement) {
          $form[$field_ajax]['widget'][$subelement]['value']['#attributes'][$event] = $js;
        }
      }
    }

    if ($operation === 'save' && !$form_state->getErrors()) {
      $form['confirm_message'] = [
        '#markup' => $this->t('Updated'),
      ];
    }

    if ($fallback && $operation && $operation !== 'cancel') {
      $form['cancel'] = [
        '#type' => 'submit',
        '#op' => 'cancel',
        '#value' => $this->t('Cancel'),
        '#weight' => 20,
        '#ajax' => [
          'callback' => [$this, 'ajaxCallback'],
          'wrapper' => $wrapper,
          'disable-refocus' => TRUE,
        ],
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild();
    $trigger = $form_state->getTriggeringElement();
    if (!empty($trigger['#op'])) {
      $form_state->set('operation', $trigger['#op']);
      // No further processing for edit and cancel.
      if ($trigger['#op'] !== 'save') {
        return;
      }
    }

    // Make sure we load fresh entity to prevent data loss:
    // https://www.drupal.org/project/editablefields/issues/3292392
    $entity = $this->entityTypeManager
      ->getStorage($this->entity->getEntityTypeId())
      ->load($this->entity->id());

    // Check if the entity has a getTranslation method
    // (e.g. node, taxonomy term).
    // If so, get the translation and update the field on the translated entity.
    if (method_exists($entity, 'getTranslation') &&
      ($langcode = $form_state->getValue('entity_language')) &&
      ($translation = $entity->getTranslation($langcode))) {
      $entity = $translation;
    }

    if (!$entity) {
      return;
    }

    $field = $this->field_name;
    $form_display = $this->getFormDisplay();

    if (!$form_display || !$form_display->id()) {
      return;
    }

    // Update the entity.
    if ($form_display->getComponent($field)) {
      $widget = $form_display->getRenderer($field);
      if (!$widget) {
        return;
      }

      $items = $entity->get($field);
      $items->filterEmptyItems();
      $widget->extractFormValues($items, $form, $form_state);
      if (method_exists($entity, 'setSyncing')) {
        $entity->setSyncing(TRUE);
      }
      $entity->save();
    }
  }

  /**
   * Editable field ajax callback.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return array|\Drupal\Core\Ajax\AjaxResponse
   *   Updated form.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $route = $this->routeMatch->getRouteName();
    if ($route === 'editablefields.get_from') {
      $params = $this->routeMatch->getParameters()->all();
      $response = new AjaxResponse();
      $command = new CloseModalDialogCommand();
      $response->addCommand($command);

      if (!empty($params['display_mode'])) {
        $entity = $this->entityTypeManager
          ->getStorage($params['entity_type'])
          ->load($params['entity_id']);

        if ($entity) {
          $formatter = $entity->get($params['field_name'])
            ->view($params['display_mode']);
          $command = new ReplaceCommand('.' . $params['selector'] . ' .field', $formatter);
          $response->addCommand($command);
        }
      }
      return $response;
    }

    return $form;
  }

  /**
   * Loads a form display mode.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|NULL
   *   Display mode.
   */
  public function getFormDisplay() {
    return $this->editablefieldsHelper->getFormDisplay(
      $this->entity,
      $this->form_mode
    );
  }

  /**
   * Set defaults to be used for unique form ID.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Edited entity.
   * @param $field_name
   *   Field name.
   * @param $settings
   *   Form mode.
   */
  public function setDefaults(EntityInterface $entity, $field_name, array $settings) {
    $this->entity = $entity;
    $this->field_name = $field_name;
    $this->form_mode = !empty($settings['form_mode'])
      ? $settings['form_mode']
      : $this->editablefieldsHelper::DEFAULT_MODE;
    $this->settings = $settings;
  }

  /**
   * Set unique form id.
   *
   * @return string
   *   Unique part of the form ID.
   */
  public function prepareUniqueFormId() {
    return $this->editablefieldsHelper->prepareSelector(
      $this->entity,
      $this->field_name,
      $this->form_mode
    );
  }

}
