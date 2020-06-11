<?php

namespace Drupal\quiz\Controller;

use Drupal\system\Controller\SystemController;

class QuizAdminController extends SystemController {

  /**
   * {@inheritdoc}
   */
  public function overview($link_id = 'quiz.admin') {
    $build['blocks'] = parent::overview($link_id);
    return $build;
  }

}
