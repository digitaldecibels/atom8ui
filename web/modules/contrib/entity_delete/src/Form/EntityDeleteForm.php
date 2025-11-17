<?php

namespace Drupal\entity_delete\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The EntityDeleteForm class.
 *
 * @package Drupal\entity_delete\Form
 */
class EntityDeleteForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_delete_form';
  }

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The CSRF token generator.
   *
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  protected $csrfToken;

  /**
   * The entity type bundle info.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected $entityTypeBundleInfo;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * EntityDeleteForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity Delete Constructor.
   * @param \Drupal\Core\Access\CsrfTokenGenerator $csrf_token
   *   CSRF token generator.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $entity_type_bundle_info
   *   The entity type bundle info.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, CsrfTokenGenerator $csrf_token, EntityTypeBundleInfoInterface $entity_type_bundle_info, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->csrfToken = $csrf_token;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Creating Container for constructor.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   Container Interface.
   *
   * @return static
   *   Return static value.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('csrf_token'),
      $container->get('entity_type.bundle.info'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['displays'] = [];
    $input = &$form_state->getUserInput();
    $wrapper = 'entity-wrapper';
    // Create the part of the form that allows the user to select the basic
    // properties of what the entity to delete.
    $form['displays']['show'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Entity Delete Settings'),
      '#tree' => TRUE,
      '#attributes' => ['class' => ['container-inline']],
    ];
    $content_entity_types = [];
    $entity_type_definations = $this->entityTypeManager->getDefinitions();
    /** @var \Drupal\Core\Entity\EntityTypeInterface $definition */
    foreach ($entity_type_definations as $definition) {
      if ($definition instanceof ContentEntityType) {
        $content_entity_types[$definition->id()] = $definition->getLabel();
      }
    }
    $form['displays']['show']['entity_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select Entity Type'),
      '#options' => $content_entity_types,
      '#empty_option' => $this->t('-select-'),
      '#size' => 1,
      '#required' => TRUE,
      '#ajax' => [
        'callback' => [$this, 'ajaxCallChangeEntity'],
        'wrapper' => $wrapper,
      ],
    ];
    $type_options = ['all' => $this->t('All')];
    $form['displays']['show']['type'] = [
      '#type' => 'select',
      '#title' => $this->t('of type'),
      '#options' => $type_options,
      '#prefix' => '<div id="' . $wrapper . '">',
      '#suffix' => '</div>',
    ];
    if (isset($input['show']['entity_type']) && ($input['show']['entity_type'] != 'comment')) {
      $default_bundles = $this->entityTypeBundleInfo->getBundleInfo($input['show']['entity_type']);
      /*If the current base table support bundles and has more than one (like user).*/
      if (!empty($default_bundles)) {
        // Get all bundles and their human readable names.
        foreach ($default_bundles as $type => $bundle) {
          $type_options[$type] = $bundle['label'];
        }
        $form['displays']['show']['type']['#options'] = $type_options;
      }
    }
    $form['displays']['show']['comment_message'] = [
      '#type' => 'fieldset',
      '#markup' => $this->t('<br>Note: bundle. (not supported in comment entity types) Refer this <a target="_blank" href="https://www.drupal.org/node/1343708">How to use EntityFieldQuery</a>.<br>'),
      '#states' => [
        'visible' => [
          'select[name="show[entity_type]"]' => ['value' => 'comment'],
        ],
      ],
    ];
    $form['message'] = [
      '#markup' => $this->t('Note: Use <b>ENTITY DELETE</b> only to delete Comment, Content, Log Entries, Taxonomy, User(s).<br>'),
    ];
    if ($this->moduleHandler->moduleExists('commerce')) {
      $form['commerce_message'] = [
        '#markup' => $this->t('<br>And Also supports Commerce - Line Item, Product, Order, Product Attribute, Product Variation, Profile, Store</br>'),
      ];
    }
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Delete',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function ajaxCallChangeEntity(array &$form, FormStateInterface $form_state) {
    return $form['displays']['show']['type'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get $form_state values.
    $values = $form_state->getValues();
    // Entity type.
    $entity_type = $values['show']['entity_type'];
    // Get bundle.
    $bundle = $values['show']['type'];
    $url = Url::fromRoute('entity_delete.entity_delete_confirmation', [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
    ]);
    $token = $this->csrfToken->get($url->getInternalPath());
    $url->setOptions(['query' => ['token' => $token]]);
    $form_state->setRedirectUrl($url);

  }

}
