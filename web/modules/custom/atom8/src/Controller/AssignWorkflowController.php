<?php

namespace Drupal\atom8\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\Request; // We need the Request class

/**
 * Controller for creating a workflow node via JSON POST request.
 */
class AssignWorkflowController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AssignWorkflowController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * Creates a new Workflow node based on JSON data from a POST request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   * The incoming request object containing the JSON payload.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   * A JSON response with the new node ID or an error response.
   */
  public function assign(Request $request) {

    // --- 1. Get and Validate JSON Data ---

    $content = $request->getContent();
    $data = json_decode($content, TRUE); // Decode JSON into an associative array

    // Define required fields for the POST request.
    $required_keys = ['workflow_id', 'install_id', 'group_id'];
    foreach ($required_keys as $key) {
      if (empty($data[$key])) {
        $error_message = $this->t('Missing required parameter: @key.', ['@key' => $key]);
        return new JsonResponse(['status' => 'error', 'message' => $error_message], Response::HTTP_BAD_REQUEST);
      }
    }

    // Extract variables from the POST data.
    $workflow_id = $data['workflow_id'];
    $install_id = $data['install_id'];
    $group_id = $data['group_id'];
    // Optional: Use 'title' from JSON if available, otherwise it's NULL.
    $custom_title = $data['title'] ?? NULL;

    // --- 2. Entity Loading and Validation ---

    // Load Install Node.
    $install_node = $this->entityTypeManager->getStorage('node')->load($install_id);
    if (!$install_node) {
      $error_message = $this->t('Install ID @id not found.', ['@id' => $install_id]);
      return new JsonResponse(['status' => 'error', 'message' => $error_message], Response::HTTP_NOT_FOUND);
    }

    // Load Group Entity (optional for validation/use).
    $group = $this->entityTypeManager->getStorage('group')->load($group_id);
    if (!$group) {
      $error_message = $this->t('Group ID @id not found.', ['@id' => $group_id]);
      return new JsonResponse(['status' => 'error', 'message' => $error_message], Response::HTTP_NOT_FOUND);
    }

    // --- 3. Create the New Workflow Node ---

    // Use the custom title from JSON or generate a default one.
    $new_workflow_title = $custom_title ?: $this->t('Workflow @workflow_id for Install: @install_title', [
      '@workflow_id' => $workflow_id,
      '@install_title' => $install_node->label(),
    ]);

    /** @var \Drupal\node\NodeInterface $workflow_node */
    $workflow_node = Node::create([
      'type' => 'workflow',
      'title' => $new_workflow_title,
      // Assuming 'field_install_reference' exists on the 'workflow' content type.
      'field_install_reference' => $install_node->id(),
      'uid' => $this->currentUser()->id(),
      'status' => 1,
    ]);

    // Save the new workflow node.
    $workflow_node->save();

    // --- 4. Return Success Response ---

    $data = [
      'status' => 'success',
      'message' => $this->t('New Workflow node created successfully via JSON POST.'),
      'new_workflow_nid' => $workflow_node->id(),
      'assigned_title' => $new_workflow_title,
      'install_title' => $install_node->label(),
      'group_title' => $group->label(),
    ];

    return new JsonResponse($data, Response::HTTP_CREATED); // HTTP 201 Created
  }
}