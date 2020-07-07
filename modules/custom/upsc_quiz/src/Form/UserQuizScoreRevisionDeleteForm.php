<?php

namespace Drupal\upsc_quiz\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for deleting a User quiz score revision.
 *
 * @ingroup upsc_quiz
 */
class UserQuizScoreRevisionDeleteForm extends ConfirmFormBase {
  use StringTranslationTrait;

  /**
   * The User quiz score revision.
   *
   * @var \Drupal\upsc_quiz\Entity\UserQuizScoreInterface
   */
  protected $revision;

  /**
   * The User quiz score storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $userQuizScoreStorage;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->userQuizScoreStorage = $container->get('entity_type.manager')->getStorage('user_quiz_score');
    $instance->connection = $container->get('database');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_quiz_score_revision_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the revision from %revision-date?', [
      '%revision-date' => format_date($this->revision->getRevisionCreationTime()),
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.user_quiz_score.version_history', ['user_quiz_score' => $this->revision->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $user_quiz_score_revision = NULL) {
    $this->revision = $this->UserQuizScoreStorage->loadRevision($user_quiz_score_revision);
    $form = parent::buildForm($form, $form_state);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->UserQuizScoreStorage->deleteRevision($this->revision->getRevisionId());

    $this
      ->logger('content')
      ->notice('User quiz score: deleted %title revision %revision.',
        [
          '%title' => $this->revision->label(),
          '%revision' => $this->revision->getRevisionId()
        ]
      );

    $this
      ->messenger()
      ->addMessage($this->t('Revision from %revision-date of User quiz score %title has been deleted.',
        [
          '%revision-date' => format_date($this->revision->getRevisionCreationTime()), '%title' => $this->revision->label()
        ]
      )
      );
    $form_state->setRedirect(
      'entity.user_quiz_score.canonical',
       ['user_quiz_score' => $this->revision->id()]
    );
    if ($this->connection->query('SELECT COUNT(DISTINCT vid) FROM {user_quiz_score_field_revision} WHERE id = :id', [':id' => $this->revision->id()])->fetchField() > 1) {
      $form_state->setRedirect(
        'entity.user_quiz_score.version_history',
         ['user_quiz_score' => $this->revision->id()]
      );
    }
  }

}
