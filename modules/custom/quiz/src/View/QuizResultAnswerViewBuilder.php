<?php

namespace Drupal\quiz\View;

use Drupal;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\quiz\Entity\QuizResult;
use function check_markup;
use function quiz_access_to_score;
use function render;

class QuizResultAnswerViewBuilder extends EntityViewBuilder {

  /**
   * Build the response content with feedback.
   *
   * @todo d8 putting this here, but needs to be somewhere else.
   */
  public function alterBuild(array &$build, Drupal\Core\Entity\EntityInterface $entity, Drupal\Core\Entity\Display\EntityViewDisplayInterface $display, $view_mode) {
    // Add the question display if configured.
    $view_modes = \Drupal::service('entity_display.repository')->getViewModes('quiz_question');
    $view_builder = Drupal::entityTypeManager()->getViewBuilder('quiz_question');

    // Default view mode.
    $build["quiz_question_view_full"] = $view_builder->view($entity->getQuizQuestion());

    $rows = array();

    $labels = array(
      'attempt' => t('Your answer'),
      'choice' => t('Choice'),
      'score' => t('Score'),
      'answer_feedback' => t('Feedback'),
      'solution' => t('Correct answer'),
    );
    Drupal::moduleHandler()->alter('quiz_feedback_labels', $labels);

    foreach ($entity->getFeedbackValues() as $idx => $row) {
      foreach ($labels as $reviewType => $label) {
        if ((isset($row[$reviewType]))) {
          // Add to table.
          if (!is_null($row[$reviewType])) {
            $rows[$idx][$reviewType]['data'] = $row[$reviewType];
            // Add to render.
            if ($display->getComponent($reviewType)) {
              $build[$reviewType] = array(
                '#title' => $label,
                '#type' => 'item',
                '#markup' => render($row[$reviewType]),
              );
            }
          }
        }
      }
    }

    if ($entity->isEvaluated()) {
      $score = $entity->getPoints();
      if ($entity->isCorrect()) {
        $class = 'q-correct';
      }
      else {
        $class = 'q-wrong';
      }
    }
    else {
      $score = t('?');
      $class = 'q-waiting';
    }

    $quiz_result = QuizResult::load($entity->get('result_id')->getString());

    if ($quiz_result->access('update')) {
      $build['score']['#theme'] = 'quiz_question_score';
      $build['score']['#score'] = $score;
      $build['score']['#class'] = $class;
    }

    if ($rows) {
      $headers = array_intersect_key($labels, $rows[0]);
      $build['table'] = [
        '#type' => 'table',
        '#header' => $headers,
        '#rows' => $rows,
      ];
    }
    // Question feedback is dynamic.
    $build['#cache']['max-age'] = 0;

    return $build;
  }

}
