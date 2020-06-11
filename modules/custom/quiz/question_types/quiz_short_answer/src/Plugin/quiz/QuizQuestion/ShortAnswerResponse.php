<?php

namespace Drupal\quiz_short_answer\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;

/**
 * Extension of QuizResultAnswer.
 */
class ShortAnswerResponse extends QuizResultAnswer {

  /**
   * {@inheritdoc}
   */
  public function score(array $values) {
    $question = $this->getQuizQuestion();

    $correct = $question->get('short_answer_correct')->getString();

    $this->set('short_answer', $values['answer']);

    switch ($question->get('short_answer_evaluation')->getString()) {
      case ShortAnswerQuestion::ANSWER_MANUAL:
        $this->set('is_evaluated', 0);
        break;
      case ShortAnswerQuestion::ANSWER_MATCH:
        $this->set('is_evaluated', 1);
        if ($values['answer'] == $correct) {
          return $question->getMaximumScore();
        }
        break;

      case ShortAnswerQuestion::ANSWER_INSENSITIVE_MATCH:
        $this->set('is_evaluated', 1);
        if (strtolower($values['answer']) == strtolower($correct)) {
          return $question->getMaximumScore();
        }
        break;

      case ShortAnswerQuestion::ANSWER_REGEX:
        $this->set('is_evaluated', 1);
        if (preg_match($correct, $values['answer']) > 0) {
          return $question->getMaximumScore();
        }
        break;
    }
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse() {
    return $this->get('short_answer')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues() {
    $data = array();
    $score = $this->getPoints();
    $max = $this->getMaxScore(FALSE);

    if ($this->isEvaluated()) {
      // Question has been graded.
      if ($score == 0) {
        $icon = QuizUtil::icon('incorrect');
      }
      if ($score > 0) {
        $icon = QuizUtil::icon('almost');
      }
      if ($score == $max) {
        $icon = QuizUtil::icon('correct');
      }
    }
    else {
      $icon = QuizUtil::icon('unknown');
    }

    $data[] = array(
      // Hide this column. Does not make sense for short answer as there are no
      // choices.
      'choice' => NULL,
      'attempt' => $this->get('short_answer')->getString(),
      'correct' => $icon,
      'score' => !$this->isEvaluated() ? t('This answer has not yet been scored.') : $this->getPoints(),
      'solution' => $this->getQuizQuestion()->get('short_answer_correct')->getString(),
      'answer_feedback' => check_markup($this->get('answer_feedback')->getValue()[0]['value'],$this->get('answer_feedback')->getValue()[0]['format']),
    );

    return $data;
  }

}
