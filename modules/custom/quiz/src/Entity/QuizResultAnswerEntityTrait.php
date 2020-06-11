<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizQuestionRelationship;
use Drupal\quiz\Entity\QuizResult;
use Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion\MultichoiceResponse;
use Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion\TrueFalseResponse;
use function _quiz_question_response_get_instance;
use function drupal_static;
use function node_load;

/**
 * Each question type must store its own response data and be able to calculate
 * a score for that data.
 */
trait QuizResultAnswerEntityTrait {

  /**
   * Get the question of this question response.
   *
   * @return QuizQuestion
   */
  public function getQuizQuestion() {
    return Drupal::entityTypeManager()->getStorage('quiz_question')->loadRevision($this->get('question_vid')->getString());
  }

  /**
   * Get the result of this question response.
   *
   * @return QuizResult
   */
  public function getQuizResult() {
    return Drupal::entityTypeManager()->getStorage('quiz_result')->load($this->get('result_id')->getString());
  }

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   *
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   *
   * @return bool
   */
  public function isEvaluated() {
    return (bool) $this->get('is_evaluated')->getString();
  }

  /**
   * Check to see if the answer is marked as correct.
   *
   * This default version returns TRUE if the score is equal to the maximum
   * possible score. Each question type can determine on its own if the question
   * response is "correct". For example a multiple choice question with 4
   * correct answers could be considered correct in different configurations.
   *
   * @return bool
   */
  public function isCorrect() {
    // Need to check this - ashutosh
    return ($this->getPoints() > 0);
  }

  /**
   * Get the scaled awarded points.
   *
   * This is marked as final to make sure that no question overrides this and
   * causes reporting issues.
   *
   * @return float
   *   The user's scaled awarded points for this question.
   */
  public final function getPoints() {
    return (float) $this->get('points_awarded')->getString();
  }

  /**
   * Get the related question relationship from this quiz result answer.
   *
   * @return QuizQuestionRelationship
   */
  public function getQuestionRelationship() {
    $quiz_result = QuizResult::load($this->get('result_id')->getString());
    $relationships = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
      'quiz_id' => $quiz_result->get('qid')->getString(),
      'quiz_vid' => $quiz_result->get('vid')->getString(),
      'question_id' => $this->get('question_id')->getString(),
      'question_vid' => $this->get('question_vid')->getString(),
    ]);
    if ($relationships) {
      return reset($relationships);
    }
  }

  /**
   * Creates the report form for the admin pages.
   *
   * @return array|null
   *   An renderable FAPI array
   */
  public function getReportForm() {
    // Add general data, and data from the question type implementation.
    $form = array();

    $form['display_number'] = array(
      '#type' => 'value',
      '#value' => $this->display_number,
    );

    $form['score'] = $this->getReportFormScore();
    $form['answer_feedback'] = $this->getReportFormAnswerFeedback();
    return $form;
  }

  /**
   * Get the response part of the report form.
   *
   * @return array
   *   Array of response data, with each item being an answer to a response. For
   *   an example, see MultichoiceResponse::getFeedbackValues(). The sub items
   *   are keyed by the feedback type. Providing a NULL option means that
   *   feedback will not be shown. See an example at
   *   LongAnswerResponse::getFeedbackValues().
   */
  public function getFeedbackValues() {
    $data = array();

    $data[] = array(
      'choice' => 'True',
      'attempt' => 'Did the user choose this?',
      'correct' => 'Was their answer correct?',
      'score' => 'Points earned for this answer',
      'answer_feedback' => 'Feedback specific to the answer',
      'question_feedback' => 'General question feedback for any answer',
      'solution' => 'Is this choice the correct solution?',
      'quiz_feedback' => 'Quiz feedback at this time',
    );

    return $data;
  }

  /**
   * Get the feedback form for the reportForm.
   *
   * @return array|false
   *   An renderable FAPI array, or FALSE if no answer form.
   */
  public function getReportFormAnswerFeedback() {
    $feedback = $this->get('answer_feedback')->getValue()[0];
    return array(
      '#title' => t('Enter feedback'),
      '#type' => 'text_format',
      '#default_value' => $feedback['value'] ?: '',
      '#format' => $feedback['format'] ?: filter_default_format(),
      '#attributes' => array('class' => array('quiz-report-score')),
    );
  }

  /**
   * Calculate the unscaled score in points for this question response.
   *
   * @param array $values
   *   A part of form state values with the question input from the user.
   *
   * @return int
   *   The unscaled point value of the answer.
   */
  abstract public function score(array $values);

  /**
   * Get the user's response.
   *
   * @return mixed
   *   The answer given by the user
   */
  abstract public function getResponse();

  /**
   * Implementation of getReportFormScore().
   *
   * @see QuizQuestionResponse::getReportFormScore()
   */
  public function getReportFormScore() {
    $score = ($this->isEvaluated()) ? $this->getPoints() : '';
    return array(
      '#title' => 'Enter score',
      '#type' => 'number',
      '#default_value' => $score,
      '#min' => 0,
      '#max' => $this->getMaxScore(),
      '#attributes' => array('class' => array('quiz-report-score')),
      '#required' => TRUE,
      '#field_suffix' => '/ ' . $this->getMaxScore(),
    );
  }

  /**
   * Get answers for a question in a result.
   *
   * This static method assists in building views for the mass export of
   * question answers.
   *
   * It is not as easy as instantiating all the question responses and returning
   * the answer. To do this in views scalably we have to gather the data
   * carefully.
   *
   * This base method provides a very poor way of gathering the data.
   *
   * @see views_handler_field_prerender_list for the expected return value.
   *
   * @see MultichoiceResponse::viewsGetAnswers() for a correct approach
   * @see TrueFalseResponse::viewsGetAnswers() for a correct approach
   */
  public static function viewsGetAnswers(array $result_answer_ids = array()) {
    $items = array();
    foreach ($result_answer_ids as $result_answer_id) {
      $ra = entity_load_single('quiz_result_answer', $result_answer_id);
      $question = node_load($ra->question_nid, $ra->question_vid);
      /* @var $ra_i QuizQuestionResponse */
      $ra_i = _quiz_question_response_get_instance($ra->result_id, $question);
      $items[$ra->result_id][] = array('answer' => $ra_i->getResponse());
    }
    return $items;
  }

  /**
   * Get the weighted score ratio.
   *
   * This returns the ratio of the weighted score of this question versus the
   * question score. For example, if the question is worth 10 points in the
   * associated quiz, but it is a 3 point multichoice question, the weighted
   * ratio is 3.33.
   *
   * This is marked as final to make sure that no question overrides this and
   * causes reporting issues.
   *
   * @return float
   *   The weight of the question
   */
  public function getWeightedRatio() {
    if ($this->getMaxScore() == 0) {
      return 0;
    }

    // getMaxScore() will get the relationship max score.
    // getMaximumScore() gets the unscaled question max score.
    return $this->getMaxScore() / $this->getQuizQuestion()->getMaximumScore();
  }

  /**
   * Indicate whether the response has been evaluated (scored) yet.
   *
   * Questions that require human scoring (e.g. essays) may need to manually
   * toggle this.
   *
   * @return bool
   */
  public function isAnswered() {
    return (bool) !$this->get('answer_timestamp')->isEmpty();
  }

  /**
   * Indicate if the question was marked as skipped.
   *
   * @return bool
   */
  public function isSkipped() {
    return (bool) $this->get('is_skipped')->getString();
  }

}
