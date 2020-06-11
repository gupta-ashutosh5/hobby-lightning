<?php

namespace Drupal\quiz\Util;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\quiz\Entity\Quiz;
use Drupal\quiz\Entity\QuizResult;
use function drupal_get_path;

/**
 * Utility functions that don't belong anywhere else.
 */
class QuizUtil {

  /**
   * Callback to dynamically populate some base field defaults from global or
   * per-user settings.
   *
   * @param Quiz $quiz
   * @param BaseFieldDefinition $field
   *
   * @return mixed
   */
  static function baseFieldDefault(Quiz $quiz, BaseFieldDefinition $field) {
    $config = \Drupal::config('quiz.defaults');
    return $config->get($field->getName());
  }

  /**
   * Helper function to facilitate icon display, like "correct" or "selected".
   *
   * @param string $type
   *
   * @return array
   *   Render array.
   */
  static function icon($type) {
    $options = array();

    switch ($type) {
      case 'correct':
        $options['path'] = 'check_008000_64.png';
        $options['alt'] = t('Correct');
        break;

      case 'incorrect':
        $options['path'] = 'times_ff0000_64.png';
        $options['alt'] = t('Incorrect');
        break;

      case 'unknown':
        $options['path'] = 'question_808080_64.png';
        $options['alt'] = t('Unknown');
        break;

      case 'should':
        $options['path'] = 'check_808080_64.png';
        $options['alt'] = t('Should have chosen');
        break;

      case 'should-not':
        $options['path'] = 'times_808080_64.png';
        $options['alt'] = t('Should not have chosen');
        break;

      case 'almost':
        $options['path'] = 'check_ffff00_64.png';
        $options['alt'] = t('Almost');
        break;

      case 'selected':
        $options['path'] = 'arrow-right_808080_64.png';
        $options['alt'] = t('Selected');
        break;

      case 'unselected':
        $options['path'] = 'circle-o_808080_64.png';
        $options['alt'] = t('Unselected');
        break;

      default:
        $options['path'] = '';
        $options['alt'] = '';
    }

    if (!empty($options['path'])) {
      $options['path'] = drupal_get_path('module', 'quiz') . '/images/' . $options['path'];
    }
    if (!empty($options['alt'])) {
      $options['title'] = $options['alt'];
    }

    $image = [
      '#theme' => 'image',
      '#uri' => $options['path'],
      '#alt' => $options['title'],
      '#attributes' => ['class' => ['quiz-score-icon', $type]],
    ];
    return $image;
  }

  /**
   * Use in the case where a quiz may have ended and the temporary result ID
   * must be used instead.
   *
   * @param Quiz $quiz
   * @return QuizResult
   */
  static function resultOrTemp(Quiz $quiz) {
    if (isset($_SESSION['quiz'][$quiz->id()]['result_id'])) {
      return QuizResult::load($_SESSION['quiz'][$quiz->id()]['result_id']);
    }
    elseif (isset($_SESSION['quiz']['temp']['result_id'])) {
      return QuizResult::load($_SESSION['quiz']['temp']['result_id']);
    }

    return NULL;
  }

}
