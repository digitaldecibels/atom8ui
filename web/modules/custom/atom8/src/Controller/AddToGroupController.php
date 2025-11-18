<?php

namespace Drupal\atom8\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for adding entities to groups.
 */
class AddToGroupController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new AddToGroupController object.
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
   * Adds a node to a group.
   *
   * @param int $nid
   *   The node ID.
   * @param int $gid
   *   The group ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function addNode($nid, $gid) {
    // Load the group
    $group = $this->entityTypeManager->getStorage('group')->load($gid);
    if (!$group) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Group @gid not found.', ['@gid' => $gid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Load the node
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Node @nid not found.', ['@nid' => $nid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Get the node type to construct the plugin ID
    $node_type = $node->bundle();
    $plugin_id = 'group_node:' . $node_type;

    // Check if this relationship already exists
    $existing = $group->getRelationshipsByEntity($node);
    if (!empty($existing)) {
      return new JsonResponse([
        'status' => 'info',
        'message' => $this->t('Node @nid is already in group @gid.', [
          '@nid' => $nid,
          '@gid' => $gid,
        ]),
        'node_id' => $nid,
        'node_title' => $node->label(),
        'group_id' => $gid,
        'group_label' => $group->label(),
      ], Response::HTTP_OK);
    }

    // Add the node to the group
    try {
      $group->addRelationship($node, $plugin_id);

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Node "@title" added to group "@group" successfully.', [
          '@title' => $node->label(),
          '@group' => $group->label(),
        ]),
        'node_id' => $nid,
        'node_title' => $node->label(),
        'node_type' => $node_type,
        'group_id' => $gid,
        'group_label' => $group->label(),
      ], Response::HTTP_CREATED);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Failed to add node to group: @error', ['@error' => $e->getMessage()]),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Adds a user to a group.
   *
   * @param int $uid
   *   The user ID.
   * @param int $gid
   *   The group ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function addUser($uid, $gid) {
    // Load the group
    $group = $this->entityTypeManager->getStorage('group')->load($gid);
    if (!$group) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Group @gid not found.', ['@gid' => $gid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Load the user
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('User @uid not found.', ['@uid' => $uid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Check if user is already a member
    if ($group->getMember($user)) {
      return new JsonResponse([
        'status' => 'info',
        'message' => $this->t('User @name is already a member of group @group.', [
          '@name' => $user->getDisplayName(),
          '@group' => $group->label(),
        ]),
        'user_id' => $uid,
        'user_name' => $user->getDisplayName(),
        'group_id' => $gid,
        'group_label' => $group->label(),
      ], Response::HTTP_OK);
    }

    // Add the user to the group
    try {
      $group->addMember($user);

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('User "@name" added to group "@group" successfully.', [
          '@name' => $user->getDisplayName(),
          '@group' => $group->label(),
        ]),
        'user_id' => $uid,
        'user_name' => $user->getDisplayName(),
        'group_id' => $gid,
        'group_label' => $group->label(),
      ], Response::HTTP_CREATED);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Failed to add user to group: @error', ['@error' => $e->getMessage()]),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}