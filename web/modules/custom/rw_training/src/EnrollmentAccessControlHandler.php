<?php

namespace Drupal\rw_training;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Access control handler for Enrollment entities.
 */
class EnrollmentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    // Admins can do anything for this entity type.
    if ($account->hasPermission('administer rw training')) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Owner logic: allow a user to view their own enrollment.
    $is_owner = (int) $entity->get('user_id')->target_id === (int) $account->id();

    switch ($operation) {
      case 'view':
        return AccessResult::allowedIf($is_owner)
          ->addCacheableDependency($entity)
          ->cachePerUser();

      case 'update':
      case 'delete':
        // Non-admin updates/deletes are not allowed in MVP.
        return AccessResult::forbidden()->cachePerPermissions();

      default:
        return AccessResult::neutral()->cachePerPermissions();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    // Enrollments are created programmatically via the controller.
    // Allow admins; others will use the controller route.
    return AccessResult::allowedIfHasPermission($account, 'administer rw training')
      ->cachePerPermissions();
  }

}
