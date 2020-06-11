<?php

namespace Drupal\quiz\View;

use Drupal;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\quiz\Entity\Quiz;
use function _quiz_format_duration;
use function _quiz_get_quiz_name;

class QuizViewBuilder extends EntityViewBuilder {

  public function alterBuild(array &$build, Drupal\Core\Entity\EntityInterface $entity, Drupal\Core\Entity\Display\EntityViewDisplayInterface $display, $view_mode) {
    /* @var $entity Quiz */

    $stats = array(
      array(
        array('header' => TRUE, 'width' => '25%', 'data' => t('Questions')),
        $entity->getNumberOfQuestions(),
      ),
    );

    if ($entity->get('quiz_date')->isEmpty()) {
      $stats[] = array(
        array('header' => TRUE, 'data' => t('Available')),
        t('Always'),
      );
    }
    else {
      $stats[] = array(
        array('header' => TRUE, 'data' => t('Opens')),
        $entity->get('quiz_date')->value,
      );
      $stats[] = array(
        array('header' => TRUE, 'data' => t('Closes')),
        $entity->get('quiz_date')->end_value,
      );
    }

    if (!$entity->get('time_limit')->isEmpty()) {
      $stats[] = array(
        array('header' => TRUE, 'data' => t('Time limit')),
        _quiz_format_duration($entity->get('time_limit')->getString()),
      );
    }

    if ($display->getComponent('stats')) {
      $build['stats'] = array(
        '#id' => 'quiz-view-table',
        '#theme' => 'table',
        '#rows' => $stats,
        '#weight' => -1,
      );
    }

    $available = $entity->access('take', NULL, TRUE);
    // Check the permission before displaying start button.
    if ($available->isAllowed()) {
      if (is_a($available, \Drupal\Core\Access\AccessResultReasonInterface::class)) {
        // There's a friendly success message available. Only display if we are
        // viewing the quiz.
        // @todo does not work because we cannot pass allowed reason, only
        // forbidden reason. The message is displayed in quiz_quiz_access().
        if (\Drupal::routeMatch() == 'entity.quiz.canonical') {
          Drupal::messenger()->addMessage($available->getReason());
        }
      }
      // Add a link to the take tab.
      $link = Drupal\Core\Link::createFromRoute(t('Start @quiz', array('@quiz' => _quiz_get_quiz_name())), 'entity.quiz.take', ['quiz' => $entity->id()], ['attributes' => ['class' => array('quiz-start-link')]]);
      $build['take'] = array(
        '#cache' => ['max-age' => 0],
        '#markup' => $link->toString(),
        '#weight' => 2,
      );
    }
    else {
      $build['take'] = array(
        '#cache' => ['max-age' => 0],
        '#markup' => '<div class="quiz-not-available">' . $available->getReason() . '</div>',
        '#weight' => 2,
      );
    }
  }

}
