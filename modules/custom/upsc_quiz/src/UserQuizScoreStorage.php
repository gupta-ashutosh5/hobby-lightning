<?php

namespace Drupal\upsc_quiz;

use Drupal\Core\Entity\Sql\SqlContentEntityStorage;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\upsc_quiz\Entity\UserQuizScoreInterface;

/**
 * Defines the storage handler class for User quiz score entities.
 *
 * This extends the base storage class, adding required special handling for
 * User quiz score entities.
 *
 * @ingroup upsc_quiz
 */
class UserQuizScoreStorage extends SqlContentEntityStorage implements UserQuizScoreStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function revisionIds(UserQuizScoreInterface $entity) {
    return $this->database->query(
      'SELECT vid FROM {user_quiz_score_revision} WHERE id=:id ORDER BY vid',
      [':id' => $entity->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function userRevisionIds(AccountInterface $account) {
    return $this->database->query(
      'SELECT vid FROM {user_quiz_score_field_revision} WHERE uid = :uid ORDER BY vid',
      [':uid' => $account->id()]
    )->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function countDefaultLanguageRevisions(UserQuizScoreInterface $entity) {
    return $this->database->query('SELECT COUNT(*) FROM {user_quiz_score_field_revision} WHERE id = :id AND default_langcode = 1', [':id' => $entity->id()])
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function clearRevisionsLanguage(LanguageInterface $language) {
    return $this->database->update('user_quiz_score_revision')
      ->fields(['langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED])
      ->condition('langcode', $language->getId())
      ->execute();
  }

}
