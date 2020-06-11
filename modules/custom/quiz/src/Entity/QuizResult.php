<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\rules\Engine\RulesComponent;
use Drupal\user\EntityOwnerTrait;
use const QUIZ_KEEP_ALL;
use const QUIZ_KEEP_BEST;
use const QUIZ_KEEP_LATEST;
use function count;
use function quiz_get_feedback_options;

/**
 * Defines the Quiz entity class.
 *
 * @ContentEntityType(
 *   id = "quiz_result",
 *   label = @Translation("Quiz result"),
 *   label_collection = @Translation("Quiz results"),
 *   label_singular = @Translation("quiz result"),
 *   label_plural = @Translation("quiz results"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz result",
 *     plural = "@count quiz results",
 *   ),
 *   bundle_label = @Translation("Quiz result type"),
 *   bundle_entity_type = "quiz_result_type",
 *   admin_permission = "administer quiz_result",
 *   permission_granularity = "entity_type",
 *   base_table = "quiz_result",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_result_type.edit_form",
 *   show_revision_ui = FALSE,
 *   entity_keys = {
 *     "id" = "result_id",
 *     "published" = "released",
 *     "owner" = "uid",
 *     "bundle" = "type",
 *     "uuid" = "uuid",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\quiz\View\QuizResultViewBuilder",
 *     "access" = "Drupal\quiz\Access\QuizResultAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\quiz\Entity\QuizResultEntityForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/quiz/{quiz}/result/{quiz_result}",
 *     "edit-form" = "/quiz/{quiz}/result/{quiz_result}/edit",
 *     "delete-form" = "/quiz/{quiz}/result/{quiz_result}/delete"
 *   }
 * )
 */
class QuizResult extends \Drupal\Core\Entity\ContentEntityBase implements \Drupal\user\EntityOwnerInterface, Drupal\Core\Entity\EntityChangedInterface {

  use EntityOwnerTrait;
  use EntityChangedTrait;

  /**
   * Get the layout for this quiz result.
   *
   * The layout contains the questions to be delivered.
   *
   * @return QuizResultAnswer[]
   */
  public function getLayout() {
    if ($this->isNew()) {
      // New results do not have layouts yet.
      return [];
    }

    $quiz_result_answers = Drupal::entityTypeManager()
      ->getStorage('quiz_result_answer')
      ->loadByProperties([
      'result_id' => $this->id(),
    ]);

    // @todo when we load the layout we have to load the question relationships
    // too because we need to know the parentage
    $quiz_question_relationship = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
      'quiz_vid' => $this->get('vid')->getString(),
    ]);
    $id_qqr = [];
    foreach ($quiz_question_relationship as $rel) {
      $id_qqr[$rel->get('question_id')->getString()] = $rel;
    }


    $layout = [];
    foreach ($quiz_result_answers as $quiz_result_answer) {
      $layout[$quiz_result_answer->get('number')->getString()] = $quiz_result_answer;
      if (isset($id_qqr[$quiz_result_answer->get('question_id')->getString()])) {
        // Question is in a relationship.
        // @todo better way to do this? We need to load the relationship
        // hierarchy onto the result answers.
        $quiz_result_answer->qqr_id = $id_qqr[$quiz_result_answer->get('question_id')->getString()]->get('qqr_id')->getString();
        $quiz_result_answer->qqr_pid = $id_qqr[$quiz_result_answer->get('question_id')->getString()]->get('qqr_pid')->getString();
      }
    }

    ksort($layout, SORT_NUMERIC);

    return $layout;
  }

  /**
   * Get the label for this quiz result.
   *
   * @return string
   */
  public function label() {
    $quiz = $this->getQuiz();
    $user = $this->get('uid')->referencedEntities()[0];

    return t('@user\'s @quiz result in "@title"', array(
      '@user' => $user->getDisplayName(),
      '@quiz' => _quiz_get_quiz_name(), '@title' => $quiz->get('title')->getString(),
    ));
  }

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['result_id'] = BaseFieldDefinition::create('integer')
      ->setRequired(TRUE)
      ->setLabel('Quiz result ID');

    $fields['qid'] = BaseFieldDefinition::create('entity_reference')
      ->setRequired(TRUE)
      ->setSetting('target_type', 'quiz')
      ->setLabel(t('Quiz'));

    $fields['vid'] = BaseFieldDefinition::create('integer')
      ->setRequired(TRUE)
      ->setLabel('Quiz revision ID');

    $fields['time_start'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Attempt start time');

    $fields['time_end'] = BaseFieldDefinition::create('timestamp')
      ->setLabel('Attempt end time');

    $fields['released'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Released')
      ->setDefaultValue(0);

    $fields['score'] = BaseFieldDefinition::create('decimal')
      ->setLabel('Score');

    $fields['is_invalid'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setLabel('Invalid');

    $fields['is_evaluated'] = BaseFieldDefinition::create('boolean')
      ->setDefaultValue(0)
      ->setLabel('Evaluated');

    $fields['attempt'] = BaseFieldDefinition::create('integer')
      ->setRequired(TRUE)
      ->setDefaultValue(1)
      ->setLabel('Attempt');

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setRequired(TRUE)
      ->setLabel('Result type');

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel('Changed');

    return $fields;
  }

  /**
   * Save the Quiz result and do any post-processing to the result.
   *
   * @param type $this
   * @param \DatabaseTransaction $transaction
   *
   * @return bool
   */
  public function save() {
    if ($this->get('time_start')->isEmpty()) {
      $this->set('time_start', \Drupal::time()->getRequestTime());
    }


    $new = $this->isNew();

    if ($new) {
      // New attempt, we need to set the attempt number if there are previous
      // attempts.
      if ($this->get('uid')->getString() == 0) {
        // If anonymous, the attempt is always 1.
        $this->attempt = 1;
      }
      else {
        // Get the highest attempt number.
        $efq = \Drupal::entityQuery('quiz_result');
        $result = $efq->range(0, 1)
          ->condition('qid', $this->get('qid')->getString())
          ->condition('uid', $this->get('uid')->getString())
          ->sort('attempt', 'DESC')
          ->execute();
        if (!empty($result)) {
          $keys = array_keys($result);
          $existing = QuizResult::load(reset($keys));
          $this->set('attempt', $existing->get('attempt')->getString() + 1);
        }
      }
    }

    // Save the Quiz result.
    if (!$new) {
      $original = \Drupal::entityTypeManager()->getStorage('quiz_result')->loadUnchanged($this->id());
    }
    parent::save();

    // Post process the result.
    if ($new) {
      $quiz = \Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($this->get('vid')->getString());

      // Create question list.
      $questions = $quiz->buildLayout();
      if (empty($questions)) {
        \Drupal::messenger()->addError(t('Not enough questions were found. Please add more questions before trying to take this @quiz.', array('@quiz' => _quiz_get_quiz_name())));
        return FALSE;
      }

      $i = 0;
      $j = 0;
      foreach ($questions as $question) {
        $quizQuestion = \Drupal::entityTypeManager()
          ->getStorage('quiz_question')
          ->loadRevision($question['vid']);
        $quiz_result_answer = QuizResultAnswer::create(array(
            'result_id' => $this->id(),
            'question_id' => $question['qqid'],
            'question_vid' => $question['vid'],
            'type' => $quizQuestion->bundle(),
            'tid' => !empty($question['tid']) ? $question['tid'] : NULL,
            'number' => ++$i,
            'display_number' => $quizQuestion->isQuestion() ? ++$j : NULL,
        ));
        $quiz_result_answer->save();
      }

    }

    if (isset($original) && !$original->get('is_evaluated')->value && $this->get('is_evaluated')->value) {
      // Quiz is finished! Delete old results if necessary.
      $this->maintainResults();
    }
  }

  function getAccount() {
    return $this->get('uid')->referencedEntities()[0];
  }

  /**
   * Mark results as invalid for a quiz according to the keep results setting.
   *
   * This function will only mark the results as invalid. The actual delete
   * action happens based on a cron run.
   * If we would have deleted the results in this function the user might not
   * have been able to view the result screen of the quiz he just finished.
   *
   * @param QuizResult $quiz_result
   *   The result of the latest result for the current user.
   *
   * @return bool
   *   TRUE if results were marked as invalid, FALSE otherwise.
   */
  function maintainResults() {
    $db = \Drupal::database();
    $quiz = $this->getQuiz();
    $user = $this->getAccount();

    // Do not delete results for anonymous users.
    if ($user->id() == 0) {
      return FALSE;
    }

    $result_ids = array();
    switch ((int) $quiz->get('keep_results')->getString()) {
      case QUIZ_KEEP_ALL:
        break;

      case QUIZ_KEEP_BEST:
        $best_result_id = $db->select('quiz_result', 'qnr')
          ->fields('qnr', array('result_id'))
          ->condition('qnr.qid', $quiz->id())
          ->condition('qnr.uid', $user->id())
          ->condition('qnr.is_evaluated', 1)
          ->condition('qnr.is_invalid', 0)
          ->orderBy('score', 'DESC')
          ->execute()
          ->fetchField();
        if ($best_result_id) {
          $result_ids = $db->select('quiz_result', 'qnr')
            ->fields('qnr', array('result_id'))
            ->condition('qnr.qid', $quiz->id())
            ->condition('qnr.uid', $user->id())
            ->condition('qnr.is_evaluated', 1)
            ->condition('qnr.is_invalid', 0)
            ->condition('qnr.result_id', $best_result_id, '!=')
            ->execute()
            ->fetchCol('result_id');
        }
        break;

      case QUIZ_KEEP_LATEST:
        $result_ids = $db->select('quiz_result', 'qnr')
          ->fields('qnr', array('result_id'))
          ->condition('qnr.qid', $quiz->id())
          ->condition('qnr.uid', $user->id())
          ->condition('qnr.is_evaluated', 1)
          ->condition('qnr.is_invalid', 0)
          ->condition('qnr.result_id', $this->id(), '!=')
          ->execute()
          ->fetchCol('result_id');
        break;
    }

    if ($result_ids) {
      $db->update('quiz_result')
        ->fields(array(
          'is_invalid' => 1,
        ))
        ->condition('result_id', $result_ids, 'IN')
        ->execute();
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Update the session for this quiz to the active question.
   *
   * @param int $question_number
   *   Question number starting at 1.
   */
  function setQuestion($question_number) {
    $_SESSION['quiz'][$this->get('qid')->getString()]['current'] = $question_number;
  }

  /**
   * Score a quiz result.
   */
  function finalize() {
    $questions = $this->getLayout();

    // Mark all missing answers as blank. This is essential here for when we may
    // have pages of unanswered questions. Also kills a lot of the skip code that
    // was necessary before.
    foreach ($questions as $qinfo) {
      // If the result answer has not been marked as skipped and it hasn't been
      // answered.
      if (empty($qinfo->is_skipped) && empty($qinfo->answer_timestamp)) {
        $qinfo->is_skipped = 1;
        $qinfo->save();
      }
    }

    $score = $this->score();

    if (!isset($score['numeric_score'])) {
      $score['numeric_score'] = 0;
    }

    // @todo Could be removed if we implement any "released" functionality.
    $this->set('released', 1);

    $this->set('is_evaluated', $score['is_evaluated']);
    $this->set('score', $score['numeric_score']);
    $this->set('time_end', \Drupal::time()->getRequestTime());
    $this->save();
    return $this;
  }

  /**
   * Calculates the score user received on quiz.
   *
   * @param $quiz
   *   The quiz node.
   * @param $result_id
   *   Quiz result ID.
   *
   * @return array
   *   Contains five elements:
   *     - question_count
   *     - possible_score
   *     - numeric_score
   *     - percentage_score
   *     - is_evaluated
   */
  function score() {
    $quiz_result_answers = $this->getLayout();

    $numeric_score = 0;

    $is_evaluated = 1;

    foreach ($quiz_result_answers as $quiz_result_answer) {
      // Get the scaled point value for this question response.
      $numeric_score += $quiz_result_answer->getPoints();
      if (!$quiz_result_answer->isEvaluated()) {
        $is_evaluated = 0;
      }
    }

    return array(
      'question_count' => count($quiz_result_answers),
      'numeric_score' => $numeric_score,
      'is_evaluated' => $is_evaluated,
    );
  }

  /**
   * {@inheritdoc}
   *
   * Delete all result answers when a result is deleted.
   */
  public function delete() {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_result_answer')
      ->loadByProperties(['result_id' => $this->id()]);
    foreach ($entities as $entity) {
      $entity->delete();
    }
    //\Drupal::entityTypeManager()->getStorage('quiz_result_answer')->delete($entities);

    parent::delete();
  }

  /**
   * Find a result that is not the same as the passed result.
   *
   * Note: the Quiz result does not have an actually exist - in that case, it
   * will return the first completed result found.
   *
   * // @todo what?
   * // Oh, this is to find a result for build-on-last.
   */
  public function findOldResult() {
    $efq = \Drupal::entityQuery('quiz_result');
    $result = $efq->condition('uid', $this->get('uid')->getString())
      ->condition('qid', $this->get('qid')->getString())
      ->condition('vid', $this->get('vid')->getString())
      ->condition('result_id', (int) $this->id(), '!=')
      ->condition('time_start', 0, '>')
      ->sort('time_start', 'DESC')
      ->range(0, 1)
      ->execute();
    if (!empty($result)) {
      return QuizResult::load(key($result));
    }
    return NULL;
  }

  /**
   * Can the quiz taker view any reviews right now?
   * ASHUTOSH
   *
   * @return bool
   */
  public function hasReview() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Quiz results are never viewed outside of a Quiz, so we enforce that a Quiz
   * route parameter is added.
   */
  public function toUrl($rel = 'canonical', array $options = array()) {
    $url = parent::toUrl($rel, $options);
    $url->setRouteParameter('quiz', $this->get('qid')->getString());
    return $url;
  }

  /**
   * Get the Quiz of this result.
   *
   * @return Quiz
   */
  public function getQuiz() {
    return Drupal::entityTypeManager()->getStorage('quiz')->loadRevision($this->get('vid')->getString());
  }

  /**
   * Copy this result's answers onto another Quiz result, based on the new Quiz
   * result's settings.
   *
   * @param QuizResult $result_new
   *   An empty QuizResult.
   */
  function copyToQuizResult(QuizResult $result_new) {
    // Re-take all the questions.
    foreach ($this->getLayout() as $qra) {
      if (($qra->isCorrect()) && !$qra->isSkipped()) {
        // Populate answer.
        $duplicate = $qra->createDuplicate();
        $duplicate->set('uuid', \Drupal::service('uuid')->generate());
      }
      else {
        // Create new answer.
        $duplicate = QuizResultAnswer::create([
            'type' => $qra->bundle(),
        ]);
        foreach ($qra->getFields() as $name => $field) {
          /* @var $field Drupal\Core\Field\FieldItemList */
          if (!in_array($name, ['result_answer_id', 'uuid']) && is_a($field->getFieldDefinition(), '\Drupal\Core\Field\BaseFieldDefinition')) {
            // Copy any base fields, but not the answer.
            $duplicate->set($name, $field->getValue());
          }
        }
      }

      // Set new result ID.
      $duplicate->set('result_id', $result_new->id());
      $duplicate->save();
    }
  }

}
