<?php

namespace Drupal\quiz_page\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * Extension of QuizQuestionResponse.
 */
class QuizPageResponse extends QuizResultAnswer {

  public function score(array $values) {

  }

  /**
   * Implementation of isCorrect().
   *
   * @see QuizQuestionResponse::isCorrect()
   */
  public function isCorrect() {
    return TRUE;
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse() {
    return $this->answer;
  }

  /**
   * Implementation of getReportForm().
   *
   * @see QuizQuestionResponse::getReportForm()
   */
  public function getReportForm() {
    return array(
      '#no_report' => TRUE,
    );
  }

}
