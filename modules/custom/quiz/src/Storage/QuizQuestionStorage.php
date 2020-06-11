<?php

namespace Drupal\quiz\Storage;

use Drupal;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

class QuizQuestionStorage extends SqlContentEntityStorage {

  /**
   * When creating a new entity, map any object to its respective class.
   *
   * {@inheritdoc}
   */
  protected function doCreate(array $values) {
    $pluginManager = Drupal::service('plugin.manager.quiz.question');
    $plugins = $pluginManager->getDefinitions();
    $ret = $plugins[$values['type']];

    if ($ret['class']) {
      $this->entityClass = $ret['class'];
    }
    else {
      $this->entityClass = \Drupal\quiz\Entity\QuizQuestionBroken::class;
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
      if ($ret['class']) {
        $this->entityClass = $ret['class'];
      }
      else {
        $this->entityClass = \Drupal\quiz\Entity\QuizQuestionBroken::class;
      }
      $entities = parent::mapFromStorageRecords([$id => $record], $load_from_revision);
      $out += $entities;
    }
    return $out;
  }

}
