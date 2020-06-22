<?php

namespace Drupal\quiz\Entity;

use Drupal;
use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\EntityOwnerTrait;
use const QUIZ_QUESTION_ALWAYS;
use function count;

/**
 * Defines the Quiz entity class.
 *
 * @ContentEntityType(
 *   id = "quiz",
 *   label = @Translation("Quiz"),
 *   label_collection = @Translation("Quiz"),
 *   label_singular = @Translation("quiz"),
 *   label_plural = @Translation("quizzes"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz",
 *     plural = "@count quizzes",
 *   ),
 *   bundle_label = @Translation("Quiz type"),
 *   bundle_entity_type = "quiz_type",
 *   admin_permission = "administer quiz",
 *   permission_granularity = "bundle",
 *   base_table = "quiz",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_type.edit_form",
 *   show_revision_ui = TRUE,
 *   revision_table = "quiz_revision",
 *   revision_data_table = "quiz_field_revision",
 *   entity_keys = {
 *     "id" = "qid",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "title",
 *     "published" = "status",
 *     "owner" = "uid",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_user",
 *     "revision_created" = "revision_created",
 *     "revision_log_message" = "revision_log_message",
 *   },
 *   handlers = {
 *     "view_builder" = "Drupal\quiz\View\QuizViewBuilder",
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizListBuilder",
 *     "access" = "Drupal\quiz\Access\QuizAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\quiz\Entity\QuizEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *     "storage" = "Drupal\quiz\Storage\QuizStorage"
 *   },
 *   links = {
 *     "canonical" = "/quiz/{quiz}",
 *     "add-page" = "/quiz/add",
 *     "add-form" = "/quiz/add/{quiz_type}",
 *     "edit-form" = "/quiz/{quiz}/edit",
 *     "delete-form" = "/quiz/{quiz}/delete",
 *     "collection" = "/admin/quiz/quizzes",
 *     "take" = "/quiz/{quiz}/take",
 *   }
 * )
 */
class Quiz extends EditorialContentEntityBase implements EntityChangedInterface, EntityOwnerInterface, RevisionLogInterface, EntityPublishedInterface
{

  use EntityOwnerTrait;
  use EntityChangedTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type)
  {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('This is only visible to Quiz admnistrators.'))
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ]);


    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setSetting('weight', 0)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setRevisionable(TRUE)
      ->setLabel('Created');

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setRevisionable(TRUE)
      ->setLabel('Changed');


    $fields['keep_results'] = BaseFieldDefinition::create('list_integer')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setCardinality(1)
      ->setDefaultValue(2)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
      ])
      ->setSetting('allowed_values', [
        0 => 'The best',
        1 => 'The newest',
        2 => 'All',
      ])
      ->setLabel(t('Store results'))
      ->setDescription('These results should be stored for each user.');

    $fields['quiz_date'] = BaseFieldDefinition::create('daterange')
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'daterange_default',
      ])
      ->setDescription('The date and time during which this Quiz will be available. Leave blank to always be available.')
      ->setLabel(t('Quiz date'));

    $fields['time_limit'] = BaseFieldDefinition::create('integer')
      ->setDisplayConfigurable('view', TRUE)
      ->setDefaultValueCallback('\Drupal\quiz\Util\QuizUtil::baseFieldDefault')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'number',
      ])
      ->setSetting('min', 0)
      ->setDescription('Set the maximum allowed time in seconds for this Quiz. Use 0 for no limit.')
      ->setLabel(t('Time limit'));

    $fields['result_type'] = BaseFieldDefinition::create('entity_reference')
      ->setSetting('target_type', 'quiz_result_type')
      ->setRequired(TRUE)
      ->setDefaultValue('quiz_result')
      ->setDisplayConfigurable('form', TRUE)
      ->setRevisionable(TRUE)
      ->setLabel(t('Result type to use'));

    $fields['quiz_terms'] = BaseFieldDefinition::create('entity_reference_revisions')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'paragraph')
      ->setSetting('handler_settings', ['target_bundles' => ['quiz_question_term_pool' => 'quiz_question_term_pool']])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_paragraphs',
      ])
      ->setLabel('Quiz terms');

    return $fields;
  }

  /**
   * Add a question to this quiz.
   *
   * @param QuizQuestion $quiz_question
   *
   * @return \Drupal\quiz\Entity\QuizQuestionRelationship
   *   Newly created or found QuizQuestionRelationship.
   * @todo return value may change
   *
   */
  function addQuestion(QuizQuestion $quiz_question)
  {
    $relationships = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_id' => $this->id(),
        'quiz_vid' => $this->getRevisionId(),
        'question_id' => $quiz_question->id(),
        'question_vid' => $quiz_question->getRevisionId(),
      ]);

    if (empty($relationships)) {
      // Save a new relationship.
      $qqr = QuizQuestionRelationship::create([
        'quiz_id' => $this->id(),
        'quiz_vid' => $this->getRevisionId(),
        'question_id' => $quiz_question->id(),
        'question_vid' => $quiz_question->getRevisionId(),
      ]);
      $qqr->save();
      return $qqr;
    } else {
      return reset($relationships);
    }

    // @todo update the max score of the quiz.
    // quiz_update_max_score_properties(array($quiz->vid));
  }

  /**
   * Delete all quiz results and question relationships when a quiz is deleted.
   *
   * @todo This should probably gather keys instead of loading all entities and
   * looping through to ensure their hooks get fired.
   *
   * {@inheritdoc}
   */
  public function delete()
  {
    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties(['quiz_id' => $this->id()]);
    foreach ($entities as $entity) {
      $entity->delete();
    }
    Drupal::entityTypeManager()->getStorage('quiz_question_relationship')->delete($entities);

    $entities = \Drupal::entityTypeManager()
      ->getStorage('quiz_result')
      ->loadByProperties(['qid' => $this->id()]);
    foreach ($entities as $entity) {
      $entity->delete();
    }
    Drupal::entityTypeManager()->getStorage('quiz_result')->delete($entities);

    parent::delete();
  }

  /**
   * Retrieves a list of questions (to be taken) for a given quiz.
   *
   * If the quiz has random questions this function only returns a random
   * selection of those questions. This function should be used to decide
   * what questions a quiz taker should answer.
   *
   * This question list is stored in the user's result, and may be different
   * when called multiple times. It should only be used to generate the layout
   * for a quiz attempt and NOT used to do operations on the questions inside of
   * a quiz.
   *
   * @return QuizResultAnswer[]
   *   ???
   */
  function buildLayout()
  {
    $questions = array();

    // Get required questions first.
    $query = \Drupal::database()->query('SELECT qqr.question_id as qqid, qqr.question_vid as vid, qq.type, qqr.qqr_id, qqr.qqr_pid, qq.title
    FROM {quiz_question_relationship} qqr
    JOIN {quiz_question} qq ON qqr.question_id = qq.qqid
    LEFT JOIN {quiz_question_relationship} qqr2 ON (qqr.qqr_pid = qqr2.qqr_id OR (qqr.qqr_pid IS NULL AND qqr.qqr_id = qqr2.qqr_id))
    WHERE qqr.quiz_vid = :quiz_vid
    AND qqr.question_status = :question_status
    ORDER BY qqr2.weight, qqr.weight', array(':quiz_vid' => $this->getRevisionId(), ':question_status' => QUIZ_QUESTION_ALWAYS));
    $i = 0;
    while ($question_node = $query->fetchAssoc()) {
      // Just to make it easier on us, let's use a 1-based index.
      $i++;
      $questions[$i] = $question_node;
    }


    $count = 0;
    $display_count = 0;
    $questions_out = array();
    foreach ($questions as &$question) {
      $count++;
      $display_count++;
      $question['number'] = $count;
      if ($question['type'] != 'page') {
        $question['display_number'] = $display_count;
      }
      $questions_out[$count] = $question;
    }
    return $questions_out;
  }

  /**
   * Check if this Quiz revision has attempts.
   *
   * @return bool
   *   If the version of this Quiz has attempts.
   */
  function hasAttempts()
  {
    $result = \Drupal::entityQuery('quiz_result')
      ->condition('qid', $this->id())
      ->condition('vid', $this->getRevisionId())
      ->range(0, 1)
      ->execute();
    return !empty($result);
  }

  /**
   * Get the number of required questions for a quiz.
   *
   * @return int
   *   Number of required questions.
   */
  function getNumberOfRequiredQuestions()
  {
    $query = Drupal::entityQuery('quiz_question_relationship');
    $query->condition('quiz_vid', $this->getRevisionId());
    $query->condition('question_status', QUIZ_QUESTION_ALWAYS);
    $result = $query->execute();
    return count($result);
  }

  /**
   * Finds out the number of configured questions for the quiz.
   *
   * @return int
   *   The number of quiz questions.
   */
  function getNumberOfQuestions()
  {
    $count = 0;
    $relationships = $this->getQuestions();
    foreach ($relationships as $relationship) {
      if ($quizQuestion = $relationship->getQuestion()) {
        if ($quizQuestion->isGraded()) {
          $count++;
        }
      }
    }
    return intval($count);
  }

  /**
   * Show the finish button?
   */
  function isLastQuestion()
  {
    $quiz_result = QuizResult::load($_SESSION['quiz'][$this->id()]['result_id']);
    $current = $_SESSION['quiz'][$this->id()]['current'];
    $layout = $quiz_result->getLayout();

    foreach ($layout as $idx => $qra) {
      if ($qra->get('question_id')->referencedEntities()[0]->bundle() == 'page') {
        if ($current == $idx) {
          // Found a page that we are on.
          $in_page = TRUE;
          $last_page = TRUE;
        } else {
          // Found a quiz page that we are not on.
          $last_page = FALSE;
        }
      } elseif (empty($qra->qqr_pid)) {
        // A question without a parent showed up.
        $in_page = FALSE;
        $last_page = FALSE;
      }
    }

    return $last_page || !isset($layout[$_SESSION['quiz'][$this->id()]['current'] + 1]);
  }

  /**
   * Store old revision ID for copying questions.
   */
  function createDuplicate()
  {
    $vid = $this->getRevisionId();
    $dupe = parent::createDuplicate();
    $dupe->old_vid = $vid;
    return $dupe;
  }

  /**
   * Retrieve list of published questions assigned to quiz.
   *
   * This function should be used for question browsers and similiar... It should
   * not be used to decide what questions a user should answer when taking a quiz.
   * quiz_build_question_list is written for that purpose.
   *
   * @return
   *   An array of questions.
   */
  function getQuestions()
  {
    $relationships = Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_id' => $this->id(),
        'quiz_vid' => $this->getRevisionId(),
      ]);
    return $relationships;

    $questions = array();
    $query = db_select('quiz_question', 'n');
    $query->fields('n', array('qqid', 'type'));
    $query->fields('nr', array('vid', 'title'));
    $query->fields('qnr', array('question_status', 'weight', 'auto_update_max_score', 'qnr_id', 'qqr_pid', 'question_id', 'question_vid'));
    $query->addField('n', 'vid', 'latest_vid');
    $query->join('quiz_question_revision', 'nr', 'n.qid = nr.qid');
    $query->leftJoin('quiz_question_relationship', 'qnr', 'nr.vid = qnr.child_vid');
    $query->condition('n.status', 1);
    $query->condition('qnr.quiz_id', $quiz_nid);
    if ($quiz_vid) {
      $query->condition('qnr.quiz_vid', $quiz_vid);
    }
    $query->condition('qqr_pid', NULL, 'IS');
    $query->orderBy('qnr.weight');

    $result = $query->execute();
    foreach ($result as $question) {
      $questions[] = $question;
      quiz_get_sub_questions($question->qnr_id, $questions);
    }

    return $questions;
  }

  /**
   * Copy questions to a new quiz revision.
   *
   * @param Quiz $old_quiz
   *   The old quiz revision.
   */
  function copyFromRevision(Quiz $old_quiz)
  {
    $quiz_questions = \Drupal::entityTypeManager()
      ->getStorage('quiz_question_relationship')
      ->loadByProperties([
        'quiz_vid' => $old_quiz->getRevisionId(),
      ]);

    foreach ($quiz_questions as $quiz_question) {
      $new_question = $quiz_question->createDuplicate();
      $new_question->set('quiz_vid', $this->getRevisionId());
      $new_question->set('quiz_id', $this->id());
      $old_id = $quiz_question->id();
      $new_question->save();
      $new_questions[$old_id] = $new_question;
    }

    foreach ($new_questions as $old_id => $quiz_question) {
      if (!$quiz_question->get('qqr_pid')->isEmpty()) {
        $quiz_question->set('qqr_pid', $new_questions[$quiz_question->get('qqr_pid')->getString()]->id());
        $quiz_question->save();
      }
    }
  }

}
