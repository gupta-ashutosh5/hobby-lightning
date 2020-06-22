<?php

namespace Drupal\quiz\View;

use Drupal;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\user\Entity\User;
use function _quiz_get_quiz_name;
use function check_markup;

class QuizResultViewBuilder extends EntityViewBuilder {

  use \Drupal\Core\Messenger\MessengerTrait;

  public function alterBuild(array &$build, Drupal\Core\Entity\EntityInterface $entity, Drupal\Core\Entity\Display\EntityViewDisplayInterface $display, $view_mode) {
    /* @var $entity QuizResult */
    $render_controller = Drupal::entityTypeManager()
      ->getViewBuilder('quiz_result_answer');


    if (!$entity->is_evaluated && empty($_POST)) {
      $msg = t('Parts of this @quiz have not been evaluated yet. The score below is not final.', array('@quiz' => _quiz_get_quiz_name()));
      $this->messenger()->addWarning($msg);
    }

    $score = $entity->score();

    $account = User::load($entity->get('uid')->getString());

    if ($display->getComponent('questions')) {
      $questions = array();
      foreach ($entity->getLayout() as $qra) {
        // Loop through all the questions and get their feedback.
        $question = Drupal::entityTypeManager()->getStorage('quiz_question')->loadRevision($qra->get('question_vid')->getString());

        if (!$question) {
          // Question went missing...
          continue;
        }
      }
      if ($questions) {
        $build['questions'] = $questions;
      }
    }

    if ($display->getComponent('score')) {
      $params = array(
        '%num_correct' => $score['numeric_score'],
        '@username' => ($account->id() == $account->id()) ? t('You') : theme('username', array('account' => $account)),
        '@yourtotal' => ($account->id() == $account->id()) ? t('Your') : t('Total'),
      );

      // Show score.
      $build['score']['#markup'] = '<div id="quiz_score_possible">' . t('@username got %num_correct.', $params) . '</div>' . "\n";
    }

    if (!\Drupal\Core\Render\Element::children($build)) {
      $build['no_feedback_text']['#markup'] = t('You have finished this @quiz.', array('@quiz' => _quiz_get_quiz_name()));
    }

    return $build;
  }

  /**
   * Get the summary message for a completed quiz result.
   *
   * Summary is determined by the pass/fail configurations on the quiz.
   *
   * @param QuizResult $quiz_result
   *   The quiz result.
   *
   * @return
   *   Render array.
   */
  function getSummaryText(QuizResult $quiz_result) {
    $config = Drupal::config('quiz.settings');
    $quiz = Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($quiz_result->get('vid')->getString());
    $token = Drupal::token();

    $account = $quiz_result->get('uid')->referencedEntities()[0];
    $token_types = array(
      'global' => NULL,
      'node' => $quiz,
      'user' => $account,
      'quiz_result' => $quiz_result,
    );
    $summary = array();

    if ($paragraph = $this->getRangeFeedback($quiz, $quiz_result->get('score')->getString())) {
      // Found quiz feedback based on a grade range.
      $token = Drupal::token();
      $paragraph_text = $paragraph->get('quiz_feedback')->get(0)->getValue();
      $summary['result'] = check_markup($token->replace($paragraph_text['value'], $token_types), $paragraph_text['format']);
    }

    return $summary;
  }

  /**
   * Get summary text for a particular score from a set of result options.
   *
   * @param Quiz $quiz
   *   The quiz.
   * @param int $score
   *   The percentage score.
   *
   * @return Paragraph
   */
  function getRangeFeedback($quiz, $score) {
    foreach ($quiz->get('result_options')->referencedEntities() as $paragraph) {
      $range = $paragraph->get('quiz_feedback_range')->get(0)->getValue();
      if ($score >= $range['from'] && $score <= $range['to']) {
        return $paragraph;
      }
    }
  }

}
