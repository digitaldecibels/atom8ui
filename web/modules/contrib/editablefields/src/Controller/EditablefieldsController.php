<?php

namespace Drupal\editablefields\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\editablefields\services\EditableFieldsHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for Editable Fields routes.
 */
class EditablefieldsController extends ControllerBase {

  /**
   * the EditableFieldsHelper service.
   *
   * @var \Drupal\editablefields\services\EditableFieldsHelper
   */
  protected $editablefieldsHelper;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\editablefields\services\EditableFieldsHelper $editablefields_helper
   *   The EditableFieldsHelper service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EditableFieldsHelper $editablefields_helper, EntityTypeManagerInterface $entity_type_manager) {
    $this->editablefieldsHelper = $editablefields_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('editablefields.helper'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * Builds the response.
   */
  public function getForm($entity_type, $entity_id, $form_mode, $field_name, $display_mode = NULL) {
    try {
      $entity = $this->entityTypeManager
        ->getStorage($entity_type)
        ->load($entity_id);
    } catch (InvalidPluginDefinitionException|PluginNotFoundException $e) {
      return [
        '#markup' => $this->t('Error occurred. Please contact site admin'),
      ];
    }

    if (!$entity) {
      return [
        '#markup' => $this->t('Error occurred. Please contact site admin'),
      ];
    }

    return $this->editablefieldsHelper->getForm(
      $entity,
      $field_name,
      ['form_mode' => $form_mode]
    );
  }

}
