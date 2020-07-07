<?php

/**
 * @file
 * Contains user_quiz_score.page.inc.
 *
 * Page callback for User quiz score entities.
 */

use Drupal\Core\Render\Element;

/**
 * Prepares variables for User quiz score templates.
 *
 * Default template: user_quiz_score.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the user information and any
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_user_quiz_score(array &$variables) {
  // Helpful $content variable for templates.
  foreach (Element::children($variables['elements']) as $key) {
    $variables['content'][$key] = $variables['elements'][$key];
  }
}
