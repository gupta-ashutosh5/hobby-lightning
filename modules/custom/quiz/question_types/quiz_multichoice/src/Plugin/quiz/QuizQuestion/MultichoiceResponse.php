<?php

namespace Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion;

use Drupal\quiz\Entity\QuizResultAnswer;
use Drupal\quiz\Util\QuizUtil;
use function _quiz_question_response_get_instance;
use function check_markup;
use function db_delete;
use function db_query;
use function db_select;
use function entity_load;
use function node_load_multiple;

/**
 * Extension of QuizQuestionResponse.
 */
class MultichoiceResponse extends QuizResultAnswer
{

  /**
   * ID of the answers.
   */
  protected $user_answer_ids;
  protected $choice_order;

  /**
   * {@inheritdoc}
   */
  public function score($response)
  {
    if (!is_array($response['answer']['user_answer'])) {
      $selected_vids = [$response['answer']['user_answer']];
    } else {
      $selected_vids = $response['answer']['user_answer'];
    }

    // Reset whatever was here already.
    $this->get('multichoice_answer')->setValue(NULL);

    // The answer ID is the revision ID of the Paragraph item of the MCQ.
    // Fun!
    foreach ($selected_vids as $vid) {
      // Loop through all selected answers and append them to the paragraph
      // revision reference.
      $this->get('multichoice_answer')->appendItem($vid);
    }

    $simple = $this->getQuizQuestion()->get('choice_boolean')->getString();
    $multi = $this->getQuizQuestion()->get('choice_multi')->getString();

    $score = 0;

    foreach ($this->getQuizQuestion()->get('alternatives')->referencedEntities() as $alternative) {
      // Take action on each alternative being selected (or not).
      $vid = $alternative->getRevisionId();
      // If this alternative was selected.
      $selected = in_array($vid, $selected_vids);

      if ($selected) {
        // Selected this answer, simple scoring on, and the answer was incorrect.
        $score += $alternative->get('multichoice_score_chosen')->getString();
        break;
      }
    }

    return $score;
  }

  /**
   * Implementation of getResponse().
   *
   * @see QuizQuestionResponse::getResponse()
   */
  public function getResponse()
  {
    $vids = [];
    foreach ($this->get('multichoice_answer')->getValue() as $alternative) {
      $vids[] = $alternative['value'];
    }
    return $vids;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedbackValues()
  {
    // @todo d8
    //$this->orderAlternatives($this->question->alternatives);
    $simple_scoring = $this->getQuizQuestion()->get('choice_boolean')->getString();

    $user_answers = $this->getResponse();

    $data = array();
    foreach ($this->getQuizQuestion()->get('alternatives')->referencedEntities() as $alternative) {
      $chosen = in_array($alternative->getRevisionId(), $user_answers);
      $not = $chosen ? '' : 'not_';

      $data[] = array(
        'choice' => check_markup($alternative->multichoice_answer->value, $alternative->multichoice_answer->format),
        'attempt' => $chosen ? QuizUtil::icon('selected') : '',
        'score' => (float)$alternative->{"multichoice_score_{$not}chosen"}->value,
        'answer_feedback' => check_markup($alternative->{"multichoice_feedback_{$not}chosen"}->value, $alternative->{"multichoice_feedback_{$not}chosen"}->format),
        'solution' => $alternative->multichoice_score_chosen->value > 0 ? QuizUtil::icon('should') : ($simple_scoring ? QuizUtil::icon('should-not') : ''),
      );
    }

    return $data;
  }

  /**
   * Order the alternatives according to the choice order stored in the database.
   *
   * @param array $alternatives
   *   The alternatives to be ordered.
   */
  protected function orderAlternatives(array &$alternatives)
  {
    if (!$this->question->choice_random) {
      return;
    }
    $result = db_query('SELECT choice_order FROM {quiz_multichoice_user_answers}
            WHERE result_answer_id = :raid', array(':raid' => $this->result_answer_id))->fetchField();
    if (!$result) {
      return;
    }
    $order = explode(',', $result);
    $newAlternatives = array();
    foreach ($order as $value) {
      foreach ($alternatives as $alternative) {
        if ($alternative['id'] == $value) {
          $newAlternatives[] = $alternative;
          break;
        }
      }
    }
    $alternatives = $newAlternatives;
  }

  /**
   * Get answers for a question in a result.
   *
   * This static method assists in building views for the mass export of
   * question answers.
   *
   * @see views_handler_field_prerender_list for the expected return value.
   */
  public static function viewsGetAnswers(array $result_answer_ids = array())
  {
    $ras = entity_load('quiz_result_answer', $result_answer_ids);
    $items = array();
    $nids = db_select('quiz_node_results_answers', 'qra')
      ->fields('qra', array('question_nid'))
      ->condition('result_answer_id', $result_answer_ids)
      ->execute()
      ->fetchAllKeyed(0, 0);
    $nodes = node_load_multiple($nids);
    foreach ($ras as $ra) {
      $question = $nodes[$ra->question_nid];
      /* @var $ra_i QuizQuestionResponse */
      $ra_i = _quiz_question_response_get_instance($ra->result_id, $question);

      $alternatives = array();
      foreach ($question->alternatives as $alternative) {
        $alternatives[$alternative['id']] = $alternative;
      }

      foreach ($ra_i->getResponse() as $answer_id) {
        if (!empty($answer_id)) {
          $items[$ra->result_id][] = array('answer' => $alternatives[$answer_id]['answer']['value']);
        }
      }
    }

    return $items;
  }

}
