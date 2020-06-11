<?php

namespace Drupal\quiz\Access;

use Drupal;
use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

class QuizResultAccessControlHandler extends Drupal\entity\UncacheableEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    if ($operation == 'view') {
      if (Drupal::currentUser()->hasPermission('view results for own quiz') && $account->id() == $entity->getQuiz()->get('uid')->getString()) {
        // User can view all quiz results for a quiz they authorized.
        return AccessResultAllowed::allowed();
      }
      if (Drupal::currentUser()->hasPermission('view own quiz_result') && $account->id() == $entity->get('uid')->getString()) {
        // User can view their own quiz result.
        return AccessResultAllowed::allowed();
      }
    }

    if ($operation == 'update') {
      if (Drupal::currentUser()->hasPermission('score own quiz') && $account->id() == $entity->getQuiz()->get('uid')->getString()) {
        // User can view all quiz results for a quiz they authored.
        return AccessResultAllowed::allowed();
      }
    }

    return parent::checkAccess($entity, $operation, $account);
  }

}
