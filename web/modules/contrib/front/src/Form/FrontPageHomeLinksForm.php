<?php

namespace Drupal\front_page\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure site information settings for this site.
 */
class FrontPageHomeLinksForm extends ConfigFormBase {

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * {@inheritdoc}
   */
  public function __construct(PathValidatorInterface $path_validator) {
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path.validator'),
    );
  }

  /**
   * Implements \Drupal\Core\Form\FormInterface::getFormID().
   */
  public function getFormId() {
    return 'front_page_admin_home_links';
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
    global $base_url;

    $config = $this->config('front_page.settings');
    $form['front_page_home_link_path'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect your site HOME links to'),
      '#default_value' => $config->get('home_link_path'),
      '#cols' => 20,
      '#rows' => 1,
      '#description' => $this->t("Specify where the user should be redirected to. An example would be <em>/node/12</em>. Leave blank when you're not using HOME redirect."),
      '#field_prefix' => $base_url,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Validate path.
    if (($value = $form_state->getValue('front_page_home_link_path')) && !str_starts_with($value, "/")) {
      $form_state->setErrorByName('front_page_home_link_path', $this->t("The path '%path' has to start with a slash.", ['%path' => $form_state->getValue('front_page_home_link_path')]));
    }
    if (!$this->pathValidator->isValid($form_state->getValue('front_page_home_link_path'))) {
      $form_state->setErrorByName('front_page_home_link_path', $this->t("Either the path '%path' is invalid or you do not have access to it.", ['%path' => $form_state->getValue('front_page_home_link_path')]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory()->getEditable('front_page.settings')
      ->set('home_link_path', $form_state->getValue('front_page_home_link_path'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
