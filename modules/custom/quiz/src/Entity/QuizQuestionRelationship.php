<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use const QUIZ_QUESTION_ALWAYS;

/**
 * Defines the Quiz entity class.
 *
 * @ContentEntityType(
 *   id = "quiz_question_relationship",
 *   label = @Translation("Quiz question relationship"),
 *   label_collection = @Translation("Quiz question relationship"),
 *   label_singular = @Translation("quiz question relationship"),
 *   label_plural = @Translation("quiz question relationships"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz question relationship",
 *     plural = "@count quiz question relationships",
 *   ),
 *   admin_permission = "administer quiz",
 *   base_table = "quiz_question_relationship",
 *   fieldable = FALSE,
 *   entity_keys = {
 *     "id" = "qqr_id",
 *   },
 *   handlers = {
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *     "form" = {
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *   },
 *   links = {
 *     "canonical" = "/quiz-question-relationship/{quiz_question_relationship}",
 *     "delete-form" = "/quiz-question-relationship/{quiz_question_relationship}/delete",
 *   }
 * )
 */
class QuizQuestionRelationship extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['qqr_pid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Question parent')
      ->setSettings(array(
      'target_type' => 'quiz_question_relationship',
    ));

    $fields['quiz_id'] = BaseFieldDefinition::create('entity_reference')
      ->setSettings(array(
        'target_type' => 'quiz',
      ))
      ->setLabel(t('Quiz'));

    $fields['quiz_vid'] = BaseFieldDefinition::create('integer')
      ->setLabel('Quiz revision ID');

    $fields['question_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel('Question ID')
      ->setSettings(array(
      'target_type' => 'quiz_question',
    ));

    $fields['question_vid'] = BaseFieldDefinition::create('integer')
      ->setLabel('Question revision ID');

    $fields['question_status'] = BaseFieldDefinition::create('integer')
      ->setDefaultValue(QUIZ_QUESTION_ALWAYS)
      ->setLabel('Question status');

    $fields['weight'] = BaseFieldDefinition::create('integer')
      ->setLabel('Weight');

    $fields['auto_update_max_score'] = BaseFieldDefinition::create('boolean')
      ->setLabel('Automatically update max score')
      ->setDefaultValue(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function save() {
    parent::save();
  }

  /**
   *
   * @return Quiz
   */
  public function getQuiz() {
    return \Drupal::entityTypeManager()
        ->getStorage('quiz')
        ->loadRevision($this->get('quiz_vid')->getString());
  }

  /**
   * Get the question associated with this relationship.
   *
   * @param bool $latest_revision
   *   Get the latest revision instead of the current.
   *
   * @return QuizQuestion
   */
  public function getQuestion($latest_revision = FALSE) {
    $storage = \Drupal::entityTypeManager()
      ->getStorage('quiz_question');

    if ($latest_revision) {
      return $storage->loadRevision($this->get('question_vid')->getString());
    }
    else {
      return $storage->loadRevision($this->get('question_id')->getString());
    }
  }

}
