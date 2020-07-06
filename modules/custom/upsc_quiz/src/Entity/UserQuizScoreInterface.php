<?php

namespace Drupal\upsc_quiz\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for defining User quiz score entities.
 *
 * @ingroup upsc_quiz
 */
interface UserQuizScoreInterface extends ContentEntityInterface, RevisionLogInterface, EntityChangedInterface, EntityPublishedInterface, EntityOwnerInterface {

  /**
   * Add get/set methods for your configuration properties here.
   */

  /**
   * Gets the User quiz score name.
   *
   * @return string
   *   Name of the User quiz score.
   */
  public function getName();

  /**
   * Sets the User quiz score name.
   *
   * @param string $name
   *   The User quiz score name.
   *
   * @return \Drupal\upsc_quiz\Entity\UserQuizScoreInterface
   *   The called User quiz score entity.
   */
  public function setName($name);

  /**
   * Gets the User quiz score creation timestamp.
   *
   * @return int
   *   Creation timestamp of the User quiz score.
   */
  public function getCreatedTime();

  /**
   * Sets the User quiz score creation timestamp.
   *
   * @param int $timestamp
   *   The User quiz score creation timestamp.
   *
   * @return \Drupal\upsc_quiz\Entity\UserQuizScoreInterface
   *   The called User quiz score entity.
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the User quiz score revision creation timestamp.
   *
   * @return int
   *   The UNIX timestamp of when this revision was created.
   */
  public function getRevisionCreationTime();

  /**
   * Sets the User quiz score revision creation timestamp.
   *
   * @param int $timestamp
   *   The UNIX timestamp of when this revision was created.
   *
   * @return \Drupal\upsc_quiz\Entity\UserQuizScoreInterface
   *   The called User quiz score entity.
   */
  public function setRevisionCreationTime($timestamp);

  /**
   * Gets the User quiz score revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   */
  public function getRevisionUser();

  /**
   * Sets the User quiz score revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return \Drupal\upsc_quiz\Entity\UserQuizScoreInterface
   *   The called User quiz score entity.
   */
  public function setRevisionUserId($uid);

}
