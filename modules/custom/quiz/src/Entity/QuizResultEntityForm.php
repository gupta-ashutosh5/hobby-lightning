<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizResult;
use const QUIZ_KEEP_BEST;

class QuizResultEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * Add the questions in this result to the edit form.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /* @var $quiz_result QuizResult */
    $quiz_result = $this->entity;
    if ($quiz_result->isNew()) {
      $form = parent::buildForm($form, $form_state);
      $form['actions']['submit']['#value'] = t('Start @quiz', array('@quiz' => _quiz_get_quiz_name()));
    }
    else {
      $form['question']['#tree'] = TRUE;
      $render_controller = Drupal::entityTypeManager()
        ->getViewBuilder('quiz_result_answer');
      foreach ($quiz_result->getLayout() as $layoutIdx => $qra) {
        $form['question'][$layoutIdx] += $qra->getReportForm();
      }

      $form = parent::buildForm($form, $form_state);
      $form['actions']['submit']['#value'] = t('Save score');
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Additionally update the score of the questions in this result.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /* @var $quiz_result QuizResult */
    $quiz_result = $this->entity;
    $layout = $this->entity->getLayout();

    // Update questions.
    foreach ($form_state->getValue('question') as $layoutIdx => $question) {
      $qra = $layout[$layoutIdx];
      $qra->set('points_awarded', $question['score']);
      $qra->set('is_evaluated', 1);
      $qra->save();
    }

    // Finalize result.
    $quiz_result->finalize();

    // Notify the user if results got deleted as a result of him scoring an
    // answer.
    $quiz = \Drupal::entityTypeManager()
      ->getStorage('quiz')
      ->loadRevision($quiz_result->get('vid')->getString());
    $results_got_deleted = $quiz_result->maintainResults();
    $add = $quiz->get('keep_results')->getString() == QUIZ_KEEP_BEST && $results_got_deleted ? ' ' . t('Note that this @quiz is set to only keep each users best answer.', array('@quiz' => _quiz_get_quiz_name())) : '';
    \Drupal::messenger()->addMessage(t('The scoring data you provided has been saved.') . $add);


    // Update the result.
    parent::submitForm($form, $form_state);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);


  }

}
