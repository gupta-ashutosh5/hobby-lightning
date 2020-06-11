<?php

/**
 * Extension of QuizQuestionResponse.
 */
class ScaleResponse extends QuizQuestionResponse {

  /**
   * ID of the answer.
   */
  protected $answer_id = 0;

  /**
   * Constructor.
   *
   * @param int $result_id
   *   The result ID for the user's result set. There is one result ID per time
   *   the user takes a quiz.
   * @param stdClass $question_node
   *   The question node.
   * @param mixed $answer
   *   The answer (dependent on question type).
   */
  public function __construct($result_id, stdClass $question_node, $answer = NULL) {
    parent::__construct($result_id, $question_node, $answer);

    if (isset($answer)) {
      $this->answer_id = intval($answer);
    }
    else {
      $this->answer_id = db_query('SELECT answer_id FROM {quiz_scale_user_answers} WHERE result_answer_id = :raid', array(':raid' => $this->result_answer_id))->fetchField();
    }
    $answer = db_query('SELECT answer FROM {quiz_scale_answer} WHERE id = :id', array(':id' => $this->answer_id))->fetchField();
    $this->answer = check_plain($answer);
  }

  /**
   * Implementation of save().
   *
   * @see QuizQuestionResponse::save()
   */
  public function save() {
    db_merge('quiz_scale_user_answers')->key(array('result_answer_id' => $this->result_answer_id))
      ->fields(array(
        'answer_id' => $this->answer_id,
        'result_answer_id' => $this->result_answer_id,
      ))
      ->execute();
  }

  /**
   * Implementation of delete().
   *
   * @see QuizQuestionResponse::delete()
   */
  public function delete() {
    db_delete('quiz_scale_user_answers')
      ->condition('result_answer_id', $this->result_answer_id)
      ->execute();
  }

  /**
   * Implementation of score().
   *
   * @see QuizQuestionResponse::score()
   */
  public function score() {
    return (bool) $this->getResponse();
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse() {
    return $this->answer_id;
  }

  /**
   * Implementation of getFeedbackValues().
   *
   * @see QuizQuestionResponse::getFeedbackValues()
   */
  public function getFeedbackValues() {
    $data = array();
    $data[] = array(
      'choice' => $this->answer,
    );
    return $data;
  }

}
