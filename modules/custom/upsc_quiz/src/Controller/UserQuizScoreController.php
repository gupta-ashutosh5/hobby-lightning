<?php

namespace Drupal\upsc_quiz\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Url;
use Drupal\upsc_quiz\Entity\UserQuizScoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserQuizScoreController.
 *
 *  Returns responses for User quiz score routes.
 */
class UserQuizScoreController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatter
   */
  protected $dateFormatter;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->dateFormatter = $container->get('date.formatter');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Displays a User quiz score revision.
   *
   * @param int $user_quiz_score_revision
   *   The User quiz score revision ID.
   *
   * @return array
   *   An array suitable for drupal_render().
   */
  public function revisionShow($user_quiz_score_revision) {
    $user_quiz_score = $this->entityTypeManager()->getStorage('user_quiz_score')
      ->loadRevision($user_quiz_score_revision);
    $view_builder = $this->entityTypeManager()->getViewBuilder('user_quiz_score');

    return $view_builder->view($user_quiz_score);
  }

  /**
   * Page title callback for a User quiz score revision.
   *
   * @param int $user_quiz_score_revision
   *   The User quiz score revision ID.
   *
   * @return string
   *   The page title.
   */
  public function revisionPageTitle($user_quiz_score_revision) {
    $user_quiz_score = $this->entityTypeManager()->getStorage('user_quiz_score')
      ->loadRevision($user_quiz_score_revision);
    return $this->t('Revision of %title from %date', [
      '%title' => $user_quiz_score->label(),
      '%date' => $this->dateFormatter->format($user_quiz_score->getRevisionCreationTime()),
    ]);
  }

  /**
   * Generates an overview table of older revisions of a User quiz score.
   *
   * @param \Drupal\upsc_quiz\Entity\UserQuizScoreInterface $user_quiz_score
   *   A User quiz score object.
   *
   * @return array
   *   An array as expected by drupal_render().
   */
  public function revisionOverview(UserQuizScoreInterface $user_quiz_score) {
    $account = $this->currentUser();
    $user_quiz_score_storage = $this->entityTypeManager()->getStorage('user_quiz_score');

    $langcode = $user_quiz_score->language()->getId();
    $langname = $user_quiz_score->language()->getName();
    $languages = $user_quiz_score->getTranslationLanguages();
    $has_translations = (count($languages) > 1);
    $build['#title'] = $has_translations ? $this->t('@langname revisions for %title', ['@langname' => $langname, '%title' => $user_quiz_score->label()]) : $this->t('Revisions for %title', ['%title' => $user_quiz_score->label()]);

    $header = [$this->t('Revision'), $this->t('Operations')];
    $revert_permission = (($account->hasPermission("revert all user quiz score revisions") || $account->hasPermission('administer user quiz score entities')));
    $delete_permission = (($account->hasPermission("delete all user quiz score revisions") || $account->hasPermission('administer user quiz score entities')));

    $rows = [];

    $vids = $user_quiz_score_storage->revisionIds($user_quiz_score);

    $latest_revision = TRUE;

    foreach (array_reverse($vids) as $vid) {
      /** @var \Drupal\upsc_quiz\UserQuizScoreInterface $revision */
      $revision = $user_quiz_score_storage->loadRevision($vid);
      // Only show revisions that are affected by the language that is being
      // displayed.
      if ($revision->hasTranslation($langcode) && $revision->getTranslation($langcode)->isRevisionTranslationAffected()) {
        $username = [
          '#theme' => 'username',
          '#account' => $revision->getRevisionUser(),
        ];

        // Use revision link to link to revisions that are not active.
        $date = $this->dateFormatter->format($revision->getRevisionCreationTime(), 'short');
        if ($vid != $user_quiz_score->getRevisionId()) {
          $link = $this->l($date, new Url('entity.user_quiz_score.revision', [
            'user_quiz_score' => $user_quiz_score->id(),
            'user_quiz_score_revision' => $vid,
          ]));
        }
        else {
          $link = $user_quiz_score->link($date);
        }

        $row = [];
        $column = [
          'data' => [
            '#type' => 'inline_template',
            '#template' => '{% trans %}{{ date }} by {{ username }}{% endtrans %}{% if message %}<p class="revision-log">{{ message }}</p>{% endif %}',
            '#context' => [
              'date' => $link,
              'username' => $this->renderer->renderPlain($username),
              'message' => [
                '#markup' => $revision->getRevisionLogMessage(),
                '#allowed_tags' => Xss::getHtmlTagList(),
              ],
            ],
          ],
        ];
        $row[] = $column;

        if ($latest_revision) {
          $row[] = [
            'data' => [
              '#prefix' => '<em>',
              '#markup' => $this->t('Current revision'),
              '#suffix' => '</em>',
            ],
          ];
          foreach ($row as &$current) {
            $current['class'] = ['revision-current'];
          }
          $latest_revision = FALSE;
        }
        else {
          $links = [];
          if ($revert_permission) {
            $links['revert'] = [
              'title' => $this->t('Revert'),
              'url' => $has_translations ?
              Url::fromRoute('entity.user_quiz_score.translation_revert', [
                'user_quiz_score' => $user_quiz_score->id(),
                'user_quiz_score_revision' => $vid,
                'langcode' => $langcode,
              ]) :
              Url::fromRoute('entity.user_quiz_score.revision_revert', [
                'user_quiz_score' => $user_quiz_score->id(),
                'user_quiz_score_revision' => $vid,
              ]),
            ];
          }

          if ($delete_permission) {
            $links['delete'] = [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.user_quiz_score.revision_delete', [
                'user_quiz_score' => $user_quiz_score->id(),
                'user_quiz_score_revision' => $vid,
              ]),
            ];
          }

          $row[] = [
            'data' => [
              '#type' => 'operations',
              '#links' => $links,
            ],
          ];
        }

        $rows[] = $row;
      }
    }

    $build['user_quiz_score_revisions_table'] = [
      '#theme' => 'table',
      '#rows' => $rows,
      '#header' => $header,
    ];

    return $build;
  }

}
