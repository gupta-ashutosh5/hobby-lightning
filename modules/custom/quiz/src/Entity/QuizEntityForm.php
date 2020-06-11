<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use function quiz_get_feedback_options;

class QuizEntityForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   *
   * Redirect to the questions form after quiz creation.
   */
  public function save(array $form, FormStateInterface $form_state) {
    parent::save($form, $form_state);
    $form_state->setRedirect('quiz.questions', ['quiz' => $this->entity->id()]);
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['quiz'] = [
      '#weight' => 5,
      '#type' => 'vertical_tabs'
    ];
    $form['availability_options'] = array(
      '#type' => 'details',
      '#title' => t('Availability options'),
      '#group' => 'quiz',
    );

    $form['quiz_feedback'] = array(
      '#type' => 'details',
      '#title' => t('Quiz feedback'),
      '#group' => 'quiz',
    );

    $form['takes']['#group'] = 'availability_options';
    $form['time_limit']['#group'] = 'availability_options';

    $form['quiz_terms']['#group'] = 'random';

    //$form['quiz_always']['#group'] = 'availability_options';
    $form['quiz_date']['#group'] = 'availability_options';

    $form['result_options']['#group'] = 'quiz_feedback';
    // Build the review options.


    if ($this->entity->hasAttempts()) {
      $override = \Drupal::currentUser()->hasPermission('override quiz revisioning');
      if (Drupal::config('quiz.settings')->get('revisioning', FALSE)) {
        $form['revision']['#required'] = !$override;
      }
      else {
        $message = $override ? t('<strong>Warning:</strong> This quiz has attempts. You can edit this quiz, but it is not recommended.<br/>Attempts in progress and reporting will be affected.<br/>You should delete all attempts on this quiz before editing.') : t('You must delete all attempts on this quiz before editing.');
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
      $form['revision']['#description'] = '<strong>Warning:</strong> This quiz has attempts.<br/>In order to update this quiz you must create a new revision.<br/>This will affect reporting.<br/>This will only affect new attempts.';
    }

    return $form;
  }

}
