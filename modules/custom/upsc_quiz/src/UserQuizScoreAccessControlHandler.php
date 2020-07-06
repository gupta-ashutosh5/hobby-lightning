<?php

namespace Drupal\upsc_quiz;

use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Access controller for the User quiz score entity.
 *
 * @see \Drupal\upsc_quiz\Entity\UserQuizScore.
 */
class UserQuizScoreAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    /** @var \Drupal\upsc_quiz\Entity\UserQuizScoreInterface $entity */

    switch ($operation) {

      case 'view':

        if (!$entity->isPublished()) {
          return AccessResult::allowedIfHasPermission($account, 'view unpublished user quiz score entities');
        }


        return AccessResult::allowedIfHasPermission($account, 'view published user quiz score entities');

      case 'update':

        return AccessResult::allowedIfHasPermission($account, 'edit user quiz score entities');

      case 'delete':

        return AccessResult::allowedIfHasPermission($account, 'delete user quiz score entities');
    }

    // Unknown operation, no opinion.
    return AccessResult::neutral();
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    return AccessResult::allowedIfHasPermission($account, 'add user quiz score entities');
  }


}
