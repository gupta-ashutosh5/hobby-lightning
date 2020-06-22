<?php

namespace Drupal\quiz\Form;

use Drupal;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\quiz\Entity\QuizResult;

class QuizQuestionAnsweringForm extends FormBase {

  /**
   * Get the form to show to the quiz taker.
   *
   * @param array $form
   * @param array $form_state
   * @param array $questions
   *   A list of question nodes to get answers from.
   * @param int $result_id
   *   The result ID for this attempt.
   *
   * @return array
   *   An renderable FAPI array.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    /* @var $questions \Drupal\quiz\Entity\QuizQuestion[] */
    $questions = $form_state->getBuildInfo()['args'][0];

    $quiz_result = QuizResult::load($form_state->getBuildInfo()['args'][1]);
    $quiz = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($quiz_result->get('vid')->getString());

    // Take quiz and result in the form.
    $form['#quiz'] = array('nid' => $quiz->nid, 'vid' => $quiz->vid);
    $form['#quiz_result'] = $quiz_result;

    if (!is_array($questions)) {
      // One single question (or page?)
      if ($questions->bundle() == 'page') {
        foreach ($quiz_result->getLayout() as $qra) {
          if ($qra->get('question_id')->getString() == $questions->id()) {
            // Found the page in the layout.
            // Add the page as the first "question".
            $questions = array($questions);
            foreach ($quiz_result->getLayout() as $qra2) {
              // Get all page children.
              if ($qra2->qqr_pid == $qra->qqr_id) {
                // This question belongs in the requested page.
                $questions[] = $qra2->getQuizQuestion();
              }
            }
            break;
          }
        }
      }
      else {
        // Make this a page of 1 question.
        $questions = array($questions->id() => $questions);
      }
    }

    $form['#attributes']['class'] = array('answering-form');
    $form['#tree'] = TRUE;

    foreach ($questions as $question) {
      $class = Html::getClass('quiz-question-' . $question->bundle());
      // Element for a single question.
      // @todo Instead of doing this load I think we can refer back to the layout.
      $qra = $question->getResponse($quiz_result);

      $element = $question->getAnsweringForm($form_state, $qra);

      // Render the question using the "question" view mode.
      $build = \Drupal::entityTypeManager()
        ->getViewBuilder('quiz_question')
        ->view($question, 'question');

      $form['question'][$question->id()] = array(
        '#attributes' => array('class' => array($class)),
        '#type' => 'container',
        'header' => !$qra->get('display_number')->isEmpty() ? array('#markup' => "<h2>" . t("Question @question", array('@question' => $qra->get('display_number')->getString())) . "</h2>") : NULL,
        'question' => $build,
        'answer' => $element,
      );
      $form['question'][$question->id()]['answer']['#quiz_result_answer'] = $qra;

      $blank_and_change = $qra->get('is_skipped')->getString();
      if (!$qra->get('answer_timestamp')->isEmpty()) {
        if ($blank_and_change) {
          // Allow it.
        }
        else {
          // This question was already answered, or answering blank question is
          // disabled.
          $form['question'][$question->id()]['#disabled'] = TRUE;
          if (empty($form_state->getUserInput())) {
            // Only show message if not submitting.
            \Drupal::messenger()->addWarning(t('Changing answers is disabled.'));
          }
        }
      }
    }

    $is_last = $quiz->isLastQuestion();

    $form['navigation']['#type'] = 'actions';
    $form['navigation']['#theme'] = 'quiz_question_navigation_form';

    $form['navigation']['submit_hidden'] = array(
      '#weight' => -9999,
      '#type' => 'submit',
      '#value' => $is_last ? t('Finish') : t('Next'),
      '#attributes' => array('style' => 'display: none'),
    );

    if ($is_last) {
      $form['navigation']['#last'] = TRUE;
      $form['navigation']['last_text'] = array(
        '#weight' => 0,
        '#markup' => '<p><em>' . t('This is the last question. Press Finish to deliver your answers') . '</em></p>',
      );
    }

    $form['navigation']['submit'] = array(
      '#weight' => 30,
      '#type' => 'submit',
      '#value' => $is_last ? t('Finish') : t('Next'),
      '#ajax' => [],
    );

    if ($is_last) {
      // Display a confirmation dialogue if this is the last question and a user
      // is able to navigate backwards but not forced to answer correctly.
      $form['#attributes']['class'][] = 'quiz-answer-confirm';
      $form['#attributes']['data-confirm-message'] = t("By proceeding you won't be able to go back and edit your answers.");
      $form['#attached']['library'][] = 'quiz/confirm';
    }

    return $form;
  }

  public function getFormId() {
    return 'quiz_question_answering_form';
  }

  /**
   * Submit handler for the question answering form.
   *
   * There is no validation code here, but there may be feedback code for
   * correct feedback.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('quiz.settings');

    $result_id = $form_state->getBuildInfo()['args'][1];
    $quiz_result = QuizResult::load($result_id);
    $quiz = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($quiz_result->get('vid')->getString());
    $time_reached = $quiz->get('time_limit')->getString() > 0 && (\Drupal::time()->getRequestTime() > ($quiz_result->get('time_start')->getString() + $quiz->get('time_limit')->getString() + $config->get('time_limit_buffer', 5)));
    $layout = $quiz_result->getLayout();

    if ($time_reached) {
      // Too late.
      // @todo move to quiz_question_answering_form_validate(), and then put all
      // the "quiz end" logic in a sharable place. We just need to not fire the
      // logic that saves all the users answers.
      \Drupal::messenger()->addError(t('The last answer was not submitted, as the time ran out.'));
    }
    else {
      $submitted = $form_state->getValue('question');

      foreach ($layout as $qra) {
        if (isset($submitted[$qra->get('question_id')->getString()])) {
          // User submitted a response to this question.
          $qqid = $qra->get('question_id')->getString();
          $qra->set('answer_timestamp', \Drupal::time()->getRequestTime());

          // Get the unscaled points awarded from the question response
          // implementation and then apply the weighted ratio in this quiz. For
          // example a MCQ question itself may be worth 4 points but worth 10
          // points in this quiz. A score of 2 would mean 5 points being
          // recorded.
          $qra->points_awarded = $qra->score($form_state->getValues()['question'][$qqid]);

          // Mark question as not skipped, in case it was skipped previously.
          $qra->is_skipped = FALSE;

          // Mark as doubtful.
          $qra->is_doubtful = !empty($form_state->getValues()['question'][$qqid]['is_doubtful']) ? 1 : 0;

          $qra->save();

          // Increment the question position.
          $quiz_result->setQuestion($qra->get('number')->getString() + 1);
        }
      }
    }

    // Wat do?
    $next_number = $_SESSION['quiz'][$quiz->id()]['current'];

    if ($time_reached || !isset($layout[$_SESSION['quiz'][$quiz->id()]['current']])) {
      // If this is the last question, finalize the quiz.
      $this->submitFinalize($form, $form_state);
    }
    else {
      // No question feedback. Go to next question.
      $form_state->setRedirect('quiz.question.take', ['quiz' => $quiz->id(), 'question_number' => $_SESSION['quiz'][$quiz->id()]['current']]);
    }
  }

  /**
   * Submit action for "leave blank".
   */
  public function submitBlank(array $form, FormStateInterface $form_state) {
    $quiz_result = QuizResult::load($form_state->getBuildInfo()['args'][1]);
    $quiz = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($quiz_result->get('vid')->getString());
    $questions = $quiz_result->getLayout();

    if (!empty($form_state->getUserInput()['question'])) {
      // Loop over all question inputs provided, and record them as skipped.
      foreach (array_keys($form_state->getUserInput()['question']) as $qqid) {

        foreach ($quiz_result->getLayout() as $idx => $qra) {
          // Find the blank submitted question in the current layout.
          if ($qra->get('question_id')->getString() == $qqid) {
            // Reset the question and mark it as taken, so restrictions like
            // backwards navigation function correctly.
            $qra->set('is_skipped', TRUE);
            $qra->set('is_correct', FALSE);
            $qra->set('points_awarded', FALSE);
            $qra->set('answer_timestamp', \Drupal::time()->getRequestTime());
            $qra->save();
          }
        }
        $quiz_result->setQuestion($_SESSION['quiz'][$quiz->id()]['current'] + 1);
      }
    }
    else {
      // Advance to next question, no input here.
      $quiz_result->setQuestion($quiz, $_SESSION['quiz'][$quiz->id()]['current'] + 1);
    }

    // Advance to next question.
    $form_state->setRedirect('quiz.question.take', ['quiz' => $quiz->id(), 'question_number' => $_SESSION['quiz'][$quiz->id()]['current']]);

    $layout = $quiz_result->getLayout();
    if (!isset($layout[$_SESSION['quiz'][$quiz->id()]['current']])) {
      // If this is the last question, finalize the quiz.
      $this->submitFinalize($form, $form_state);
    }
  }

  /**
   * Helper function to finalize a quiz attempt.
   *
   * @see quiz_question_answering_form_submit()
   * @see quiz_question_answering_form_submit_blank()
   */
  function submitFinalize(array $form, FormStateInterface $form_state) {
    $quiz_result = QuizResult::load($form_state->getBuildInfo()['args'][1]);
    $quiz = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($quiz_result->get('vid')->getString());

    // No more questions. Score quiz and finalize result.
    $quiz_result->finalize();


    $form_state->setRedirect('entity.quiz_result.canonical', ['quiz' => $quiz->id(), 'quiz_result' => $quiz_result->id()]);


    // Remove all information about this quiz from the session.
    // @todo but for anon, we might have to keep some so they could access
    // results
    // When quiz is completed we need to make sure that even though the quiz has
    // been removed from the session, that the user can still access the
    // feedback for the last question, THEN go to the results page.
    $_SESSION['quiz']['temp']['result_id'] = $quiz_result->id();
    unset($_SESSION['quiz'][$quiz->id()]);
  }

  /**
   * Submit handler for "back".
   */
  function submitBack(&$form, FormStateInterface $form_state) {
    // Back a question.
    $quiz = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($form['#quiz']['vid']->getString());
    $quiz_result = QuizResult::load($_SESSION['quiz'][$quiz->id()]['result_id']);
    $quiz_result->setQuestion($_SESSION['quiz'][$quiz->id()]['current'] - 1);

    // I think this is for going back to a page
//    $layout = $quiz_result->getLayout();
//    $question = $layout[$_SESSION['quiz'][$quiz->nid]['current']];
//    if (!empty($question['qqr_pid'])) {
//      foreach ($layout as $question2) {
//        if ($question2['qnr_id'] == $question['qqr_pid']) {
//          quiz_question_goto($quiz, $question2['number']);
//        }
//      }
//    }

    $form_state->setRedirect('quiz.question.take', ['quiz' => $quiz->id(), 'question_number' => $_SESSION['quiz'][$quiz->id()]['current']]);
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('quiz.settings');
    $quiz_result = QuizResult::load($form_state->getBuildInfo()['args'][1]);
    /* @var $quiz Drupal\quiz\Entity\Quiz */
    $quiz = \Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($quiz_result->get('vid')->getString());
    $questions = $quiz_result->getLayout();

    $time_reached = !$quiz->get('time_limit')->isEmpty() && (\Drupal::time()->getRequestTime() > ($quiz_result->get('time_start')->getString() + $quiz->get('time_limit')->getString() + $config->get('quiz_time_limit_buffer', 5)));

    if ($time_reached) {
      // Let's not validate anything, because the input won't get saved in submit
      // either.
      return;
    }
  }

}
