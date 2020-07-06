<?php

namespace Drupal\upsc_quiz;

use Drupal\Core\Entity\ContentEntityStorageInterface;
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
interface UserQuizScoreStorageInterface extends ContentEntityStorageInterface {

  /**
   * Gets a list of User quiz score revision IDs for a specific User quiz score.
   *
   * @param \Drupal\upsc_quiz\Entity\UserQuizScoreInterface $entity
   *   The User quiz score entity.
   *
   * @return int[]
   *   User quiz score revision IDs (in ascending order).
   */
  public function revisionIds(UserQuizScoreInterface $entity);

  /**
   * Gets a list of revision IDs having a given user as User quiz score author.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user entity.
   *
   * @return int[]
   *   User quiz score revision IDs (in ascending order).
   */
  public function userRevisionIds(AccountInterface $account);

  /**
   * Counts the number of revisions in the default language.
   *
   * @param \Drupal\upsc_quiz\Entity\UserQuizScoreInterface $entity
   *   The User quiz score entity.
   *
   * @return int
   *   The number of revisions in the default language.
   */
  public function countDefaultLanguageRevisions(UserQuizScoreInterface $entity);

  /**
   * Unsets the language for all User quiz score with the given language.
   *
   * @param \Drupal\Core\Language\LanguageInterface $language
   *   The language object.
   */
  public function clearRevisionsLanguage(LanguageInterface $language);

}
