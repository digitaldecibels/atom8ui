<?php

namespace Drupal\front_page\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure site information settings for this site.
 */
class FrontPageSettingsForm extends ConfigFormBase {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, PathValidatorInterface $path_validator) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('path.validator'),
    );
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormId().
   */
  public function getFormId() {
    return 'front_page_admin';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->configFactory->get('front_page.settings');

    $form['front_page_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Front page override'),
      '#description' => $this->t('Enable this if you want the front page module to manage the home page.'),
      '#default_value' => $config->get('enabled') ?: FALSE,
    ];

    $form['disable_for_administrators'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable front page redirects for the administrator role.'),
      '#description' => $this->t('If checked, admin users will never be redirected, even if the authenticated user role has a redirect enabled.'),
      '#default_value' => $config->get('disable_for_administrators') ?: FALSE,
      '#states' => [
        'visible' => [
          ':input[name="roles[authenticated][enabled]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    // Load any existing settings and build the by redirect by role form.
    $form['roles'] = [
      '#tree' => TRUE,
      '#type' => 'fieldset',
      '#title' => $this->t('Roles'),
    ];

    // Build the form for roles.
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();

    // Iterate each role.
    foreach ($roles as $rid => $role) {

      $role_config = $config->get('roles.' . $rid);
      $form['roles'][$rid] = [
        '#type' => 'details',
        '#open' => FALSE,
        '#title' => $this->t('Front page for @rolename', ['@rolename' => $role->label()]),
      ];

      $form['roles'][$rid]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable'),
        '#default_value' => $role_config['enabled'] ?? FALSE,
      ];

      $form['roles'][$rid]['weight'] = [
        '#type' => 'number',
        '#title' => $this->t('Weight'),
        '#default_value' => $role_config['weight'] ?? 0,
      ];

      $form['roles'][$rid]['path'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Path'),
        '#default_value' => $role_config['path'] ?? '',
        '#cols' => 20,
        '#rows' => 1,
        '#description' => $this->t('A redirect path can contain a full URL including get parameters and fragment string (eg "/node/51?page=5#anchor").'),
      ];
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save Settings'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // parent::validateForm($form, $form_state);.
    $rolesList = $form_state->getUserInput()['roles'];
    if ($rolesList) {
      foreach ($rolesList as $rid => $role) {
        if (!empty($role['enabled']) && empty($role['path'])) {
          $form_state->setErrorByName('roles][' . $rid . '][path', $this->t('You must set the path field for redirect mode.'));
        }
        if (!empty($role['enabled']) && ($value = $role['path']) && !str_starts_with($value, "/")) {
          $form_state->setErrorByName('roles][' . $rid . '][path', $this->t("The path '%path' has to start with a slash.", ['%path' => $role['path']]));
        }
        if (!empty($role['enabled']) && !$this->pathValidator->isValid($role['path'])) {
          $form_state->setErrorByName('roles][' . $rid . '][path', $this->t("Either the path '%path' is invalid or you do not have access to it.", ['%path' => $role['path']]));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('front_page.settings');

    // Set if all config are enabled or not.
    $config->set('enabled', $form_state->getValue('front_page_enable'));

    // Set config by role.
    $rolesList = $form_state->getUserInput()['roles'];
    if (is_array($rolesList)) {
      foreach ($rolesList as $rid => $role) {
        // If the "authenticated" authenticated role is disabled, we need to
        // disable the "disable_for_administrators" setting:
        if ($rid === 'authenticated') {
          $role['enabled'] ? $config->set('disable_for_administrators', $form_state->getValue('disable_for_administrators')) : $config->set('disable_for_administrators', FALSE);
        }
        $config->set('roles.' . $rid, $role);
      }
    }

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
