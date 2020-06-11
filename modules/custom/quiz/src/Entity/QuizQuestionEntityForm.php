<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;

class QuizQuestionEntityForm extends ContentEntityForm {

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $entity_manager = Drupal::entityTypeManager();
    $access_handler = $entity_manager->getAccessControlHandler('quiz');

    if ($qid = Drupal::request()->get('qid')) {
      // Requested addition to an existing quiz.
      $vid = Drupal::request()->get('vid');

      $quiz = \Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($vid);

      // Check if the user can add a question to the requested quiz.
      if ($access_handler->access($quiz, 'update')) {
        $form['quiz_id'] = [
          '#title' => t('Quiz ID'),
          '#type' => 'value',
          '#value' => $qid,
        ];

        $form['quiz_vid'] = [
          '#title' => t('Quiz revision ID'),
          '#type' => 'value',
          '#value' => $vid,
        ];
      }
    }

    if ($this->entity->hasBeenAnswered()) {
      $override = \Drupal::currentUser()->hasPermission('override quiz revisioning');
      if (Drupal::config('quiz.settings')->get('revisioning', FALSE)) {
        $form['revision']['#required'] = !$override;
      }
      else {
        $message = $override ? t('<strong>Warning:</strong> This question has attempts. You can edit this question, but it is not recommended.<br/>Attempts in progress and reporting will be affected.<br/>You should delete all attempts on this question before editing.') : t('You must delete all attempts on this question before editing.');
        // Revisioning is disabled.
        $form['revision_information']['#access'] = FALSE;
        $form['revision']['#access'] = FALSE;
        $form['actions']['warning'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $message,
        ];
        \Drupal::messenger()->addWarning($message);
        $form['actions']['#disabled'] = TRUE;
      }
      $form['revision']['#description'] = '<strong>Warning:</strong> This question has attempts.<br/>In order to update this question you must create a new revision.<br/>This will affect reporting.<br/>You must update the quizzes with the new revision of this question.';
    }

    return $form;
  }

  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);

    if ($qid = $form_state->getValue('quiz_id')) {
      $vid = $form_state->getValue('quiz_vid');

      /* @var $quiz Quiz */
      $quiz = Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($vid);
      $quiz->addQuestion($this->entity);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Redirect after question creation.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    if ($qid = $form_state->getValue('quiz_id')) {
      $form_state->setRedirect('quiz.questions', ['quiz' => $qid]);
    }
  }

}
