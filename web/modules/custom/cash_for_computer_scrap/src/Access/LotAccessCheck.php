<?php

namespace Drupal\cash_for_computer_scrap\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\NodeInterface;

/**
 * Checks access for the packing slip route.
 */
class LotAccessCheck implements AccessInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructs a PackingSlipAccessCheck object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   * The current user.
   */
  public function __construct(AccountProxyInterface $current_user) {
    $this->currentUser = $current_user;
  }

  /**
   * Checks access to the packing-slip URL for the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   * The node object being loaded by the route.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   * The access result.
   */
  public function access(NodeInterface $node) {
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = $this->currentUser->getAccount();

    // 1. Check if the user is an Administrator (or a role with high permissions).
    // It's generally better to check for a permission like 'administer nodes'
    // or 'administer users', but for simplicity, we check the role directly.
    if ($account->hasPermission('administer nodes')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Alternatively, check for a specific role (e.g., 'administrator'):
    // if ($account->hasRole('administrator')) {
    //   return AccessResult::allowed()->cachePerPermissions();
    // }

    // 2. Check if the user is the node's creator (author).
    if ($account->id() == $node->getOwnerId()) {
      return AccessResult::allowed()->cachePerUser();
    }

    // 3. Deny access if neither of the above conditions are met.
    // Cache the result based on the current user's roles/ID.
    return AccessResult::forbidden()->cachePerUser()->cachePerPermissions();
  }

}