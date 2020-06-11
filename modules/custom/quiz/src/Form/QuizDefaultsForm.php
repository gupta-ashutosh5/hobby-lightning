<?php

namespace Drupal\quiz\Form;

use Drupal;
use Drupal\Core\Entity\EntityType;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class QuizDefaultsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['quiz.defaults'];
  }

  public function getFormId(): string {
    return 'quiz_admin_node_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('quiz.defaults');

    $quiz = Drupal\quiz\Entity\Quiz::create([
        'type' => 'quiz',
    ]);
    $entity_type = $quiz->getEntityType();
    $fields = $quiz::baseFieldDefinitions($entity_type);
    foreach ($fields as $field_name => $field) {
      if (in_array($field_name, ['qid', 'vid', 'status'])) {
        continue;
      }
      if ($field->getType() == 'boolean') {
        $form[$field_name] = [
          '#title' => $field->getLabel(),
          '#description' => $field->getDescription(),
          '#type' => 'checkbox',
          '#default_value' => $config->get($field_name),
        ];
      }
      if ($field->getType() == 'integer') {
        $form[$field_name] = [
          '#title' => $field->getLabel(),
          '#description' => $field->getDescription(),
          '#type' => 'number',
          '#default_value' => $config->get($field_name),
        ];
      }
    }
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $this->config('quiz.defaults')
      ->setData($form_state->getValues())
      ->save();
    parent::submitForm($form, $form_state);
  }

}
