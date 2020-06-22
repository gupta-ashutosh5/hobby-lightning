<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use function _quiz_get_quiz_name;
use function db_query;
use function db_query_range;
use function entity_load;
use function filter_default_format;

/**
 * A trait all Quiz question strongly typed entity bundles must use.
 */
trait QuizQuestionEntityTrait {
  /*
   * QUESTION IMPLEMENTATION FUNCTIONS
   *
   * This part acts as a contract(/interface) between the question-types and the
   * rest of the system.
   *
   * Question types are made by extending these generic methods and abstract
   * methods.
   */

  /**
   * Allow question types to override the body field title.
   *
   * @return string
   *   The title for the body field.
   */
  public function getBodyFieldTitle() {
    return t('Question');
  }

  /**
   * Returns a node form to quiz_question_form.
   *
   * Adds default form elements, and fetches question type specific elements
   * from their implementation of getCreationForm.
   *
   * @param array $form_state
   *
   * @return array
   *   An renderable FAPI array.
   */
  public function getNodeForm(array &$form_state = NULL) {
    $user = \Drupal::currentUser();
    $form = array();

    // Mark this form to be processed by quiz_form_alter. quiz_form_alter will
    // among other things hide the revision fieldset if the user don't have
    // permission to control the revisioning manually.
    $form['#quiz_check_revision_access'] = TRUE;

    // Allow user to set title?
    if (user_access_test_user_access('edit question titles')) {
      $form['helper']['#theme'] = 'quiz_question_creation_form';
      $form['title'] = array(
        '#type' => 'textfield',
        '#title' => t('Title'),
        '#maxlength' => 255,
        '#default_value' => $this->node->title,
        '#required' => TRUE,
        '#description' => t('Add a title that will help distinguish this question from other questions. This will not be seen during the @quiz.', array('@quiz' => _quiz_get_quiz_name())),
      );
    }
    else {
      $form['title'] = array(
        '#type' => 'value',
        '#value' => $this->node->title,
      );
    }

    // Store quiz id in the form.
    $form['quiz_nid'] = array(
      '#type' => 'hidden',
    );
    $form['quiz_vid'] = array(
      '#type' => 'hidden',
    );

    if (isset($_GET['quiz_nid']) && isset($_GET['quiz_vid'])) {
      $form['quiz_nid']['#value'] = intval($_GET['quiz_nid']);
      $form['quiz_vid']['#value'] = intval($_GET['quiz_vid']);
    }

    // Identify this node as a quiz question type so that it can be recognized
    // by other modules effectively.
    $form['is_quiz_question'] = array(
      '#type' => 'value',
      '#value' => TRUE,
    );

    if (!empty($this->node->nid)) {
      if ($properties = entity_load('quiz_question', FALSE, array('nid' => $this->node->nid, 'vid' => $this->node->vid))) {
        $quiz_question = reset($properties);
      }
    }

    // Add question type specific content.
    $form = array_merge($form, $this->getCreationForm($form_state));

    if (\Drupal::config('quiz.settings')->get('auto_revisioning', 1) && $this->hasBeenAnswered()) {
      $log = t('The current revision has been answered. We create a new revision so that the reports from the existing answers stays correct.');
      $this->node->revision = 1;
      $this->node->log = $log;
    }

    return $form;
  }

  /**
   * Retrieve information relevant for viewing the node.
   *
   * (This data is generally added to the node's extra field.)
   *
   * @return array
   *   Content array.
   */
  public function getNodeView() {
    $content = array();
    return $content;
  }

  /**
   * Get the form through which the user will answer the question.
   *
   * Question types should populate the form with selected values from the
   * current result if possible.
   *
   * @param FormStateInterface $form_state
   *   Form state.
   * @param QuizResultAnswer $quizQuestionResultAnswer
   *   The quiz result answer.
   *
   * @return array
   *   Form array.
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer) {
    $form = array();
    $form['#element_validate'] = [[static::class, 'getAnsweringFormValidate']];
    return $form;
  }

  /**
   * Get the maximum possible score for this question.
   *
   * @return int
   */
  abstract public function getMaximumScore();

  /**
   * Finds out if a question has been answered or not.
   *
   * This function also returns TRUE if a quiz that this question belongs to
   * have been answered. Even if the question itself haven't been answered.
   * This is because the question might have been rendered and a user is about
   * to answer it...
   *
   * @return bool
   *   TRUE if question has been answered or is about to be answered...
   */
  public function hasBeenAnswered() {
    $result = \Drupal::entityQuery('quiz_result_answer')
      ->condition('question_vid', $this->getRevisionId())
      ->range(0, 1)
      ->execute();
    return !empty($result);
  }

  /**
   * Determines if the user can view the correct answers.
   *
   * @return true|null
   *   TRUE if the view may include the correct answers to the question.
   */
  public function viewCanRevealCorrect() {
    $user = \Drupal::currentUser();

    $reveal_correct[] = user_access_test_user_access('view any quiz question correct response');
    $reveal_correct[] = ($user->id() == $this->node->uid);
    if (array_filter($reveal_correct)) {
      return TRUE;
    }
  }

  /**
   * Utility function that returns the format of the node body.
   *
   * @return string|null
   *   The format of the node body
   */
  protected function getFormat() {
    $node = isset($this->node) ? $this->node : $this->question;
    $body = field_get_items('node', $node, 'body');
    return isset($body[0]['format']) ? $body[0]['format'] : NULL;
  }

  /**
   * Validate a user's answer.
   *
   * @param array $element
   *   The form element of this question.
   * @param mixed $form_state
   *   Form state.
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state) {
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($form_state->getCompleteForm()['#quiz']['vid']->getString());

    $qqid = $element['#array_parents'][1];

    // There was an answer submitted.
    /* @var $qra QuizResultAnswer */
    $qra = $element['#quiz_result_answer'];

    // Temporarily score the answer.
    $score = $qra->score($form_state->getValue('question')[$qqid]);

    // @todo kinda hacky here, we have to scale it temporarily so isCorrect()
    // works
    $qra->set('points_awarded', $score);
    $score = $qra->get('points_awarded')->getString();

    /**if (!$qra->isCorrect() && $qra->isEvaluated()) {
      // Show feedback after incorrect answer.
      $view_builder = Drupal::entityTypeManager()->getViewBuilder('quiz_result_answer');
      $element['feedback'] = $view_builder->view($qra);
      $element['feedback']['#weight'] = 100;
      $element['feedback']['#parents'] = [];
    }**/
  }

  /**
   * Is this question graded?
   *
   * Questions like Quiz Directions, Quiz Page, and Scale are not.
   *
   * By default, questions are expected to be gradeable
   *
   * @return bool
   */
  public function isGraded() {
    return TRUE;
  }

  /**
   * Is this "question" an actual question?
   *
   * For example, a Quiz Page is not a question, neither is a "quiz directions".
   *
   * Returning FALSE here means that the question will not be numbered, and
   * possibly other things.
   *
   * @return bool
   */
  public function isQuestion() {
    return TRUE;
  }

  /**
   * Get the response to this question in a quiz result.
   *
   * @return QuizResultAnswer
   */
  public function getResponse(QuizResult $quiz_result) {
    $entities = \Drupal::entityTypeManager()->getStorage('quiz_result_answer')->loadByProperties([
      'result_id' => $quiz_result->id(),
      'question_id' => $this->id(),
      'question_vid' => $this->getRevisionId(),
    ]);
    return reset($entities);
  }

}
