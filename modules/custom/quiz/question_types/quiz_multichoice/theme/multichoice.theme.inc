<?php

/**
 * @file
 * Theme functions for the multichoice question type.
 */

/**
 * Theme the answer part of the node view.
 *
 * @param array $variables
 *
 * @return string
 *   An HTML string representing the themed output.
 */
function theme_multichoice_answer_node_view($variables) {
  $alternatives = $variables['alternatives'];
  $show_correct = $variables['show_correct'];
  $header = array('', '');

  foreach ($alternatives as $i => $short) {
    $answer_markup = check_markup($short['answer']['value'], $short['answer']['format']);
    // Find the is_correct status.
    $is_correct = ($short['score_if_chosen'] > $short['score_if_not_chosen']);
    $image = $is_correct ? 'correct' : 'wrong';
    if (!$show_correct) {
      $image = 'unknown';
    }

    $rows[] = array(
      array(
        'width' => 64,
        'data' => array(
          '#theme' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => array(
            'class' => array(
              'quiz-score-icon',
              $image,
            ),
            'title' => $show_correct ?
            t('Score if chosen: @sc Score if not chosen: @nc', array(
              '@sc' => $short['score_if_chosen'],
              '@nc' => $short['score_if_not_chosen'],
            )) :
            t('You are not allowed to view the solution for this question'),
          ),
        ),
      ),
      $answer_markup,
    );
  }
  return theme('table', array('header' => $header, 'rows' => $rows));
}
