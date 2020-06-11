<?php

namespace Drupal\quiz\Controller;

use Drupal;
use Drupal\Core\Access\AccessResultReasonInterface;
use Drupal\Core\Entity\Controller\EntityController;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use Drupal\views\Views;

class QuizController extends EntityController {

  /**
   * Take the quiz.
   * @return type
   */
  function take(Quiz $quiz) {
    $page = [];
    $page['#cache'] = ['max-age' => 0];
    /* @var $result AccessResultReasonInterface */
    $result = $quiz->access('take', NULL, TRUE);

    $message = '';
    if (is_subclass_of($result, AccessResultReasonInterface::class)) {
      $message = $result->getReason();
    }
    $success = !$result->isForbidden();

    if (!$success) {
      // Not allowed.
      $page['body']['#markup'] = $message;
      return $page;
    }
    elseif ($message) {
      // Allowed, but we have a message.
      \Drupal::messenger()->addMessage($message);
    }
    else {
      // Create new result.
      if ($success) {
        // Test a build of questions.
        $questions = $quiz->buildLayout();
        if (empty($questions)) {
          \Drupal::messenger()->addError(t('Not enough questions were found. Please add more questions before trying to take this @quiz.', array('@quiz' => _quiz_get_quiz_name())));
          return $this->redirect('entity.quiz.canonical', ['quiz' => $quiz->id()]);
        }

        // Creat a new Quiz result.
        $quiz_result = QuizResult::create(array(
            'qid' => $quiz->id(),
            'vid' => $quiz->getRevisionId(),
            'uid' => \Drupal::currentUser()->id(),
            'type' => $quiz->get('result_type')->getString(),
        ));

        $instances = Drupal::service('entity_field.manager')->getFieldDefinitions('quiz_result', $quiz->get('result_type')->getString());
        foreach ($instances as $field_name => $field) {
          if ((is_a($field, Drupal\field\Entity\FieldConfig::class) && $field->getThirdPartySetting('quiz', 'show_field'))) {
            // We found a field to be filled out.
            $redirect_url = \Drupal\Core\Url::fromRoute('entity.quiz.take', ['quiz' => $quiz_result->getQuiz()->id()]);
            $form = \Drupal::service('entity.form_builder')->getForm($quiz_result, 'default', ['redirect' => $redirect_url]);
            return $form;
          }
        }
      }
      else {
        $page['body']['#markup'] = $result['message'];
        return $page;
      }
    }


    // New attempt.
    $quiz_result->save();
    $_SESSION['quiz'][$quiz->id()]['result_id'] = $quiz_result->id();
    $_SESSION['quiz'][$quiz->id()]['current'] = 1;
    return $this->redirect('quiz.question.take', ['quiz' => $quiz->id(), 'question_number' => 1]);
  }

  /**
   * Creates a form for quiz questions.
   *
   * Handles the manage questions tab.
   *
   * @param $node
   *   The quiz node we are managing questions for.
   * @return ???
   *   String containing the form.
   */
  function manageQuestions(Quiz $quiz) {
    $manage_questions = Drupal::formBuilder()->getForm(\Drupal\quiz\Form\QuizQuestionsForm::class, $quiz);

    $question_bank = Views::getView('quiz_question_bank')->preview();

    // Insert into vert tabs.
    $form['vert_tabs'] = array(
      '#type' => 'x', // @todo wtf?
      '#weight' => 0,
      '#default_tab' => 'edit-questions',
    );
    $form['vert_tabs']['questions'] = array(
      '#type' => 'details',
      '#title' => t('Manage questions'),
      '#group' => 'vert_tabs',
      'questions' => $manage_questions,
    );
    $form['vert_tabs']['bank'] = array(
      '#type' => 'details',
      '#title' => t('Question bank'),
      '#group' => 'vert_tabs',
      'bank' => $question_bank,
    );
    return $form;
  }

}
