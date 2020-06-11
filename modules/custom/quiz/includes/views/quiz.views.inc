<?php

/**
 * @file
 * This file provides metadata to the Views 2 API for views integration.
 */

/**
 * Implements hook_views_data_alter().
 */
function quiz_views_data_alter(&$data) {
  $data['quiz_node_results_answers']['table']['join'] = array(
    'quiz_node_properties' => array(
      'left_table' => 'quiz_node_relationship',
      'left_field' => 'child_nid',
      'field' => 'question_nid',
      'extra' => 'quiz_node_results_answers.result_id = quiz_node_results.result_id',
    ),
  );


  $data['quiz_node_results']['answers'] = array(
    'title' => t('Answers'),
    'help' => t('When rendered, this field will be replaced by all the answers.'),
    'field' => array(
      'title' => 'Answers',
      'handler' => 'views_handler_field_custom',
    ),
  );

  $data['quiz_node_results']['answer'] = array(
    'title' => t('Answer'),
    'help' => t('When rendered, this field will be replaced by a requested answer.'),
    'field' => array(
      'title' => 'Answer',
      'handler' => 'quiz_views_handler_field_quiz_question_result_answer',
    ),
  );
}

/**
 * Implements hook_views_pre_view().
 *
 * Replace the static field with dynamic fields.
 */
function quiz_views_pre_view(view $view) {
  if ($view->name == 'quiz_results') {
    $fields = $view->get_items('field');

    $quiz = node_load($view->args[0]);
    foreach ($fields as $field_name => $field) {
      if ($field['id'] == 'answers') {
        $i = 0;
        foreach ($quiz->getQuestions() as $question) {
          if ($quizQuestion = _quiz_question_get_instance($question)) {
            if ($quizQuestion->isGraded()) {
              $i++;
              $newfield = array();
              $newfield['id'] = 'answer';
              $newfield['table'] = 'quiz_node_results';
              $newfield['field'] = 'answer';
              $newfield['label'] = t('@num. @question', array('@num' => $i, '@question' => $question->title));
              $newfield['question_nid'] = $question->nid;
              $newfield['question_vid'] = $question->vid;
              $view->set_item($view->current_display, 'field', 'answer_' . $question->nid, $newfield);
            }
          }
        }
        $view->set_item($view->current_display, 'field', $field_name, NULL);
      }
    }
  }
}
