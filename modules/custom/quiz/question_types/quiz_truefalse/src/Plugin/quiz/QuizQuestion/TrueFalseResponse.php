<?php

namespace Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizQuestionResponse;
use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;

/**
 * Extension of QuizQuestionResponse.
 */
class TrueFalseResponse extends QuizResultAnswer {

  /**
   * {@inheritdoc}
   */
  public function score($response) {
    $tfQuestion = $this->getQuizQuestion();
    $this->set('truefalse_answer', $response['answer']);


    if ($response['answer'] == $tfQuestion->getCorrectAnswer()) {
      return $tfQuestion->getMaximumScore();
    }
    else {
      return 0;
    }
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse() {
    return $this->get('truefalse_answer')->getString();
  }

  /**
   * Implementation of getFeedbackValues().
   *
   * @see QuizQuestionResponse::getFeedbackValues()
   */
  public function getFeedbackValues() {

    $answer = $this->getResponse();
    if (is_numeric($answer)) {
      $answer = intval($answer);
    }

    $correct_answer = intval($this->getQuizQuestion()->getCorrectAnswer());

    $data = array();
    $data[] = array(
      'choice' => t('True'),
      'attempt' => $answer === 1 ? QuizUtil::icon('selected') : '',
      'correct' => $answer === 1 ? QuizUtil::icon($correct_answer ? 'correct' : 'incorrect') : '',
      'score' => intval($correct_answer === 1 && $answer === 1),
      'answer_feedback' => '',
      'solution' => $correct_answer === 1 ? QuizUtil::icon('should') : '',
    );

    $data[] = array(
      'choice' => t('False'),
      'attempt' => $answer === 0 ? QuizUtil::icon('selected') : '',
      'correct' => $answer === 0 ? (QuizUtil::icon(!$correct_answer ? 'correct' : 'incorrect')) : '',
      'score' => intval($correct_answer === 0 && $answer === 0),
      'answer_feedback' => '',
      'solution' => $correct_answer === 0 ? QuizUtil::icon('should') : '',
    );

    return $data;
  }

  /**
   * Get answers for a question in a result.
   *
   * This static method assists in building views for the mass export of
   * question answers.
   *
   * @todo maybe not needed in d8...?
   *
   * @see views_handler_field_prerender_list for the expected return value.
   */
  public static function viewsGetAnswers(array $result_answer_ids = array()) {
    $items = array();
//    foreach ($result_answer_ids as $result_answer_id) {
//      $ra = entity_load_single('quiz_result_answer', $result_answer_id);
//      $question = node_load($ra->question_nid, $ra->question_vid);
//      /* @var $ra_i QuizQuestionResponse */
//      $ra_i = _quiz_question_response_get_instance($ra->result_id, $question);
//      $items[$ra->result_id][] = array('answer' => $ra_i->getResponse() ? t('True') : t('False'));
//    }
    return $items;
  }

}
