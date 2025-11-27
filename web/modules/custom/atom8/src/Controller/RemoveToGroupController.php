<?php

namespace Drupal\atom8\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for removing entities from groups.
 */
class RemoveFromGroupController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new RemoveFromGroupController object.
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
   * Removes a node from a group.
   *
   * @param int $nid
   *   The node ID.
   * @param int $gid
   *   The group ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function removeNode($nid, $gid) {
    // Load group.
    $group = $this->entityTypeManager->getStorage('group')->load($gid);
    if (!$group) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Group @gid not found.', ['@gid' => $gid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Load node.
    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Node @nid not found.', ['@nid' => $nid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Check for existing relationship.
    $relationships = $group->getRelationshipsByEntity($node);
    if (empty($relationships)) {
      return new JsonResponse([
        'status' => 'info',
        'message' => $this->t('Node @nid is not in group @gid.', [
          '@nid' => $nid,
          '@gid' => $gid,
        ]),
      ], Response::HTTP_OK);
    }

    // Remove relationship(s).
    try {
      foreach ($relationships as $relationship) {
        $relationship->delete();
      }

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('Node "@title" removed from group "@group" successfully.', [
          '@title' => $node->label(),
          '@group' => $group->label(),
        ]),
        'node_id' => $nid,
        'node_title' => $node->label(),
        'group_id' => $gid,
        'group_label' => $group->label(),
      ], Response::HTTP_OK);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Failed to remove node from group: @error', ['@error' => $e->getMessage()]),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  /**
   * Removes a user from a group.
   *
   * @param int $uid
   *   The user ID.
   * @param int $gid
   *   The group ID.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function removeUser($uid, $gid) {
    // Load group.
    $group = $this->entityTypeManager->getStorage('group')->load($gid);
    if (!$group) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Group @gid not found.', ['@gid' => $gid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Load user.
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    if (!$user) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('User @uid not found.', ['@uid' => $uid]),
      ], Response::HTTP_NOT_FOUND);
    }

    // Check membership.
    $membership = $group->getMember($user);
    if (!$membership) {
      return new JsonResponse([
        'status' => 'info',
        'message' => $this->t('User @name is not a member of group @group.', [
          '@name' => $user->getDisplayName(),
          '@group' => $group->label(),
        ]),
      ], Response::HTTP_OK);
    }

    // Remove membership.
    try {
      $membership->delete();

      return new JsonResponse([
        'status' => 'success',
        'message' => $this->t('User "@name" removed from group "@group" successfully.', [
          '@name' => $user->getDisplayName(),
          '@group' => $group->label(),
        ]),
        'user_id' => $uid,
        'user_name' => $user->getDisplayName(),
        'group_id' => $gid,
        'group_label' => $group->label(),
      ], Response::HTTP_OK);
    }
    catch (\Exception $e) {
      return new JsonResponse([
        'status' => 'error',
        'message' => $this->t('Failed to remove user from group: @error', ['@error' => $e->getMessage()]),
      ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

}
