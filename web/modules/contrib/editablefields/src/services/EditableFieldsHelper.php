<?php

namespace Drupal\editablefields\services;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Class EditableFieldsHelper.
 *
 * Helper service for the "editablefields" functionality.
 */
class EditableFieldsHelper {
  use StringTranslationTrait;

  /**
   * Editablefields formatter ID.
   */
  public const FORMATTER_ID = 'editablefields_formatter';

  /**
   * Use "editablefields" permission.
   */
  public const PERMISSION = 'use editablefields';

  /**
   * Editablefields admin permission.
   */
  public const ADMIN_PERMISSION = 'administer editablefields';

  /**
   * Editablefields form class.
   */
  public const FORM_CLASS = 'Drupal\editablefields\Form\EditableFieldsForm';

  /**
   * Default form mode.
   */
  public const DEFAULT_MODE = 'default';

  /**
   * Drupal\Core\Field\FieldTypePluginManagerInterface definition.
   *
   * @var \Drupal\Core\Field\FieldTypePluginManagerInterface
   */
  protected $pluginManagerFieldFieldType;

  /**
   * Drupal\Core\Form\FormBuilderInterface definition.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Drupal\Core\DependencyInjection\ClassResolverInterface definition.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * Drupal\Core\Session\AccountProxyInterface definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Entity\EntityDisplayRepositoryInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityDisplayRepositoryInterface
   */
  protected $entityDisplayRepository;

  /**
   * Constructs a new EditableFieldsHelper object.
   */
  public function __construct(FieldTypePluginManagerInterface $plugin_manager_field_field_type, FormBuilderInterface $form_builder, ClassResolverInterface $class_resolver, AccountProxyInterface $current_user, EntityDisplayRepositoryInterface $entity_display_repository) {
    $this->pluginManagerFieldFieldType = $plugin_manager_field_field_type;
    $this->formBuilder = $form_builder;
    $this->classResolver = $class_resolver;
    $this->currentUser = $current_user;
    $this->entityDisplayRepository = $entity_display_repository;
  }

  /**
   * Make "editablefields" formatter available for all the field types.
   *
   * @param array $info
   *   An array of information on existing formatter types, as collected by the
   *   annotation discovery mechanism.
   */
  public function formatterInfoAlter(array &$info) {
    if (empty($info[self::FORMATTER_ID])) {
      return;
    }

    $info[self::FORMATTER_ID]['field_types'] = $this->getAllFieldTypes();
  }

  /**
   * Get machine names of all the field types.
   *
   * @return array
   *   Array of all field types machine names.
   */
  public function getAllFieldTypes() {
    return array_keys($this->pluginManagerFieldFieldType->getDefinitions());
  }

  /**
   * Checks if the user can use "editablefields" formatter.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   *
   * @return bool
   *   TRUE if the user can use "editablefields" formatret.
   */
  public function checkAccess(EntityInterface $entity) {
    $can_edit = $entity->access('update');
    $can_use = $this->currentUser->hasPermission(self::PERMISSION);
    return ($can_edit && $can_use);
  }

  /**
   * Check if the user has administer permission.
   *
   * @return bool
   *   TRUE if the user has administer permission.
   */
  public function isAdmin() {
    return $this->currentUser->hasPermission(self::ADMIN_PERMISSION);
  }

  /**
   * Prepares a render array of the editable field form.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param string $field_name
   *   Field name.
   * @param array $settings
   *   Settings of the editablefields formatter.
   *
   * @return array
   *   Form render array.
   */
  public function getForm(EntityInterface $entity, string $field_name, array $settings) {
    /** @var \Drupal\editablefields\Form\EditableFieldsForm $form_object */
    $form_object = $this->classResolver->getInstanceFromDefinition(
      self::FORM_CLASS
    );
    $form_object->setDefaults($entity, $field_name, $settings);
    return $this->formBuilder->getForm($form_object);
  }

  /**
   * Loads entity form display.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity object.
   * @param $form_mode
   *   Form mode.
   *
   * @return \Drupal\Core\Entity\Display\EntityFormDisplayInterface|NULL
   */
  public function getFormDisplay(EntityInterface $entity, $form_mode) {
    return $this->entityDisplayRepository->getFormDisplay(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $form_mode
    );
  }

  /**
   * Helper method to prepare the list of the form modes.
   *
   * @var string $entity_type_id
   *   Entity type ID.
   *
   * @return array
   *   Array of form modes.
   */
  public function getFormModesOptions(string $entity_type_id) {
    return $this->entityDisplayRepository->getFormModeOptions($entity_type_id);
  }


  /**
   * Helper method to prepare the list of the view modes.
   *
   * @var string $entity_type_id
   *   Entity type ID.
   *
   * @return array
   *   Array of view modes.
   */
  public function getViewModesOptions(string $entity_type_id) {
    return $this->entityDisplayRepository->getViewModeOptions($entity_type_id);
  }

  /**
   * Prepare selector.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity.
   * @param string $field_name
   *   Field name.
   * @param string $form_mode
   *   Form mode.
   *
   * @return string
   *   Selector.
   */
  public function prepareSelector(EntityInterface $entity, $field_name, $form_mode) {
    $parts = [
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->id(),
      $field_name,
      $form_mode,
    ];

    return implode('_', $parts);
  }

}
