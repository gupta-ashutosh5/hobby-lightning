<?php

namespace Drupal\quiz\Storage;

use Drupal;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class QuizResultAnswerStorage extends SqlContentEntityStorage {

  /**
   * When creating a new entity, map any object to its respective class.
   *
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    $pluginManager = Drupal::service('plugin.manager.quiz.question');
    $plugins = $pluginManager->getDefinitions();
    $ret = $plugins[$values['type']];

    if ($ret['handlers']['response']) {
      $this->entityClass = $ret['handlers']['response'];
    }
    else {
      $this->entityClass = 'Drupal\quiz\Entity\QuizResultAnswerBroken';
    }

    return parent::doCreate($values);
  }

  /**
   * When loading from the database, map any object to its respective class.
   *
   * {@inheritdoc}
   */
  protected function mapFromStorageRecords(array $records, $load_from_revision = FALSE) {
    $pluginManager = Drupal::service('plugin.manager.quiz.question');
    $plugins = $pluginManager->getDefinitions();

    $out = [];
    foreach ($records as $id => $record) {
      $ret = $plugins[$record->type];
      if ($ret['handlers']['response']) {
        $this->entityClass = $ret['handlers']['response'];
      }
      else {
        $this->entityClass = 'Drupal\quiz\Entity\QuizResultAnswerBroken';
      }
      $entities = parent::mapFromStorageRecords([$id => $record], $load_from_revision);
      $out += $entities;
    }
    return $out;
  }

}
