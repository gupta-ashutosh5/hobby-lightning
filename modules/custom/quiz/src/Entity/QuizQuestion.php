<?php

namespace Drupal\quiz\Entity;

use Drupal\Core\Entity\EditorialContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Quiz question entity class.
 *
 * @ContentEntityType(
 *   id = "quiz_question",
 *   label = @Translation("Quiz question"),
 *   label_collection = @Translation("Quiz question"),
 *   label_singular = @Translation("quiz question"),
 *   label_plural = @Translation("quiz questions"),
 *   label_count = @PluralTranslation(
 *     singular = "@count quiz questions",
 *     plural = "@count quiz questions",
 *   ),
 *   bundle_label = @Translation("Quiz question type"),
 *   bundle_entity_type = "quiz_question_type",
 *   admin_permission = "administer quiz_question",
 *   permission_granularity = "bundle",
 *   base_table = "quiz_question",
 *   fieldable = TRUE,
 *   field_ui_base_route = "entity.quiz_question_type.edit_form",
 *   show_revision_ui = TRUE,
 *   revision_table = "quiz_question_revision",
 *   revision_data_table = "quiz_question_field_revision",
 *   entity_keys = {
 *     "id" = "qqid",
 *     "revision" = "vid",
 *     "bundle" = "type",
 *     "label" = "title",
 *     "published" = "status",
 *     "uuid" = "uuid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log"
 *   },
 *   handlers = {
 *     "list_builder" = "Drupal\quiz\Config\Entity\QuizQuestionListBuilder",
 *     "access" = "Drupal\entity\UncacheableEntityAccessControlHandler",
 *     "permission_provider" = "Drupal\entity\UncacheableEntityPermissionProvider",
 *     "storage" = "Drupal\quiz\Storage\QuizQuestionStorage",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *    "form" = {
 *       "default" = "Drupal\quiz\Entity\QuizQuestionEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "views_data" = "Drupal\entity\EntityViewsData",
 *   },
 *   links = {
 *     "canonical" = "/quiz-question/{quiz_question}",
 *     "add-page" = "/quiz-question/add",
 *     "add-form" = "/quiz-question/add/{quiz_question_type}",
 *     "edit-form" = "/quiz-question/{quiz_question}/edit",
 *     "delete-form" = "/quiz-question/{quiz_question}/delete",
 *     "collection" = "/admin/quiz/questions",
 *   }
 * )
 */
abstract class QuizQuestion extends EditorialContentEntityBase {

  // For strongly typed bundles.
  use \Drupal\quiz\Entity\QuizQuestionEntityTrait;

  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setRequired(TRUE)
      ->setRevisionable(TRUE)
      ->setSetting('max_length', 255)
      ->setSetting('weight', 1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
      'type' => 'text_textfield',
    ]);


    $fields['body'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Question'))
      ->setSetting('weight', 0)
      ->setRevisionable(TRUE)
      ->setRequired(TRUE)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayOptions('form', [
      'type' => 'text_textarea',
    ]);

    $fields['changed'] = BaseFieldDefinition::create('changed');


    $fields['feedback'] = BaseFieldDefinition::create('text_long')
      ->setRevisionable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setLabel(t('Generic feedback text'))
      ->setDescription(t('Will always display if configured on the Quiz.'));

    // @todo not working yet
//    $fields['tid'] = BaseFieldDefinition::create('entity_reference_revisions')
//      ->setSetting('target_type', 'taxonomy_term')
//      //->setSetting('handler_settings', ['target_bundles' => ['quiz_question_term_pool' => 'quiz_question_term_pool']])
//      ->setDisplayConfigurable('form', TRUE)
//      ->setDisplayOptions('form', [
//        'type' => 'entity_reference_autocomplete',
//      ])
//      ->setLabel('Quiz terms');

    return $fields;
  }

  public function save() {
    parent::save();
  }

}
