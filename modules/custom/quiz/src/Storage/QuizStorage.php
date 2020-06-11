<?php

namespace Drupal\quiz\Storage;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class QuizStorage extends SqlContentEntityStorage {

  function doPreSave(EntityInterface $entity) {
    return parent::doPreSave($entity);
  }

  protected function doPostSave(EntityInterface $entity, $update) {
    /* @var $entity \Drupal\quiz\Entity\Quiz */

    if (isset($entity->old_vid)) {
      // Duplicate of quiz.
      $old_vid = $entity->old_vid;
    }

    if (!$entity->isNew() && $entity->isNewRevision()) {
      // New revision of quiz.
      $old_vid = $entity->getLoadedRevisionId();
    }

    if (isset($old_vid)) {
      $original = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($old_vid);
      $entity->copyFromRevision($original);
    }

    return parent::doPostSave($entity, $update);
  }

}
