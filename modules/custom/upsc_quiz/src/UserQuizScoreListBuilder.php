<?php

namespace Drupal\upsc_quiz;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Defines a class to build a listing of User quiz score entities.
 *
 * @ingroup upsc_quiz
 */
class UserQuizScoreListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['id'] = $this->t('User quiz score ID');
    $header['name'] = $this->t('Name');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /* @var \Drupal\upsc_quiz\Entity\UserQuizScore $entity */
    $row['id'] = $entity->id();
    $row['name'] = Link::createFromRoute(
      $entity->label(),
      'entity.user_quiz_score.edit_form',
      ['user_quiz_score' => $entity->id()]
    );
    return $row + parent::buildRow($entity);
  }

}
