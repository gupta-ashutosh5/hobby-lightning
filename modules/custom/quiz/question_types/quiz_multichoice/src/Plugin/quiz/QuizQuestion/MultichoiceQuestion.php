<?php

namespace Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion;

use Drupal;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\quiz\Entity\QuizResultAnswer;
use function check_markup;
use function db_delete;
use function db_insert;
use function db_merge;
use function db_query;
use function db_update;
use function variable_get;

/**
 * @QuizQuestion (
 *   id = "multichoice",
 *   label = @Translation("Multiple choice question"),
 *   handlers = {
 *     "response" = "\Drupal\quiz_multichoice\Plugin\quiz\QuizQuestion\MultichoiceResponse"
 *   }
 * )
 */
class MultichoiceQuestion extends QuizQuestion {

  /**
   * {@inheritdoc}
   */
  public function getAnsweringForm(FormStateInterface $form_state, QuizResultAnswer $quizQuestionResultAnswer) {
    $element = parent::getAnsweringForm($form_state, $quizQuestionResultAnswer);

    foreach ($this->get('alternatives')->referencedEntities() as $alternative) {
      /* @var $alternative Paragraph */
      $uuid = $alternative->get('uuid')->getString();
      $alternatives[$uuid] = $alternative;
    }

    // Build options list.
    $element['user_answer'] = [
      '#type' => 'tableselect',
      '#header' => ['answer' => t('Answer')],
      '#js_select' => FALSE,
      '#multiple' => $this->get('choice_multi')->getString(),
    ];

    // @todo see https://www.drupal.org/project/drupal/issues/2986517
    // There is some way to label the elements.
    foreach ($alternatives as $uuid => $alternative) {
      $vid = $alternative->getRevisionId();
      $answer_markup = check_markup($alternative->get('multichoice_answer')->getValue()[0]['value'], $alternative->get('multichoice_answer')->getValue()[0]['format']);
      $element['user_answer']['#options'][$vid]['title']['data']['#title'] = $answer_markup;
      $element['user_answer']['#options'][$vid]['answer'] = $answer_markup;
    }

    if ($this->get('choice_random')->getString()) {
      // We save the choice order so that the order will be the same in the
      // answer report.
      $element['choice_order'] = array(
        '#type' => 'hidden',
        '#value' => implode(',', $this->shuffle($element['user_answer']['#options'])),
      );
    }

    if ($quizQuestionResultAnswer->isAnswered()) {
      $choices = $quizQuestionResultAnswer->getResponse();
      if ($this->get('choice_multi')->getString()) {
        foreach ($choices as $choice) {
          $element['user_answer']['#default_value'][$choice] = TRUE;
        }
      }
      else {
        $element['user_answer']['#default_value'] = reset($choices);
      }
    }

    return $element;
  }

  /**
   * Custom shuffle function.
   *
   * It keeps the array key - value relationship intact.
   *
   * @param array $array
   *
   * @return array
   */
  private function shuffle(array &$array) {
    $newArray = array();
    $toReturn = array_keys($array);
    shuffle($toReturn);
    foreach ($toReturn as $key) {
      $newArray[$key] = $array[$key];
    }
    $array = $newArray;
    return $toReturn;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaximumScore() {
    if ($this->get('choice_boolean')->getString()) {
      // Simple scoring - can only be worth 1 point.
      return 1;
    }

    $maxes = [0];
    foreach ($this->get('alternatives')->referencedEntities() as $alternative) {
      // "Not chosen" could have a positive point amount.
      $maxes[] = max($alternative->get('multichoice_score_chosen')->getString(), $alternative->get('multichoice_score_not_chosen')->getString());
    }

    if ($this->get('choice_multi')->getString()) {
      // For multiple answers, return the maximum possible points of all
      // positively pointed answers.
      return array_sum($maxes);
    }
    else {
      // For a single answer, return the highest pointed amount.
      return max($maxes);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getAnsweringFormValidate(array &$element, FormStateInterface $form_state) {
    $mcq = $form_state->getBuildInfo()['args'][0];
    if (!$mcq->get('choice_multi')->getString() && empty($element['user_answer']['#value'])) {
      $form_state->setError($element, (t('You must provide an answer.')));
    }
    parent::getAnsweringFormValidate($element, $form_state);
  }

}
