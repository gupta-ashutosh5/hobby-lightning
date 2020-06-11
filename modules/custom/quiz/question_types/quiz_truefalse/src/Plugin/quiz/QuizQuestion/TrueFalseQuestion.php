<?php

namespace Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion;

use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;

/**
 * @QuizQuestion (
 *   id = "truefalse",
 *   label = @Translation("True/false question"),
 *   handlers = {
 *     "response" = "\Drupal\quiz_truefalse\Plugin\quiz\QuizQuestion\TrueFalseResponse"
 *   }
 * )
 */
class TrueFalseQuestion extends QuizQuestion {

  /**
   * {@inheritdoc}
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    if ($this->viewCanRevealCorrect()) {
      $answer = ($this->node->correct_answer) ? t('True') : t('False');
      $content['answers']['#markup'] = '<div class="quiz-solution">' . $answer . '</div>';
      $content['answers']['#weight'] = 2;
    }
    else {
      $content['answers'] = array(
        '#markup' => '<div class="quiz-answer-hidden">' . t('Answer hidden') . '</div>',
        '#weight' => 2,
      );
    }
    return $content;
  }

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer) {
    $element = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);
    $element += array(
      '#type' => 'radios',
      '#title' => t('Choose one'),
      '#options' => array(
        1 => t('True'),
        0 => t('False'),
      ),
    );

    if ($quizQuestionResultAnswer->isAnswered()) {
      if ($quizQuestionResultAnswer->getResponse() != '') {
        $element['#default_value'] = $quizQuestionResultAnswer->getResponse();
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state) {
    parent::getAnsweringFormValidate($element, $form_state);

    if (is_null($element['#value'])) {
      $form_state->setError($element, t('You must provide an answer.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form['correct_answer'] = array(
      '#type' => 'radios',
      '#title' => t('Correct answer'),
      '#options' => array(
        1 => t('True'),
        0 => t('False'),
      ),
      '#default_value' => isset($this->node->correct_answer) ? $this->node->correct_answer : 1,
      '#required' => TRUE,
      '#weight' => -4,
      '#description' => t('Choose if the correct answer for this question is "true" or "false".'),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * The maximum points for a true/false question is always 1.
   */
  public function getMaximumScore() {
    return 1;
  }

  /**
   * Get the correct answer to this question.
   *
   * This is a utility function. It is not defined in the interface.
   *
   * @return bool
   *   Boolean indicating if the correct answer is TRUE or FALSE
   */
  public function getCorrectAnswer() {
    return $this->get('truefalse_correct')->getString();
  }

}
