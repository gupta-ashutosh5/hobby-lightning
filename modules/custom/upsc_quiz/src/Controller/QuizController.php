<?php

namespace Drupal\upsc_quiz\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Component\Serialization\Json;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;

class QuizController extends ControllerBase {

  /**
   * @var RequestStack
   */
  protected $requestStack;

  /**
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   */
  public function __construct(RequestStack $request_stack,
                              EntityTypeManagerInterface $entity_type_manager,
                              AccountProxyInterface $current_user) {
    $this->requestStack = $request_stack;
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('entity_type.manager'),
      $container->get('current_user')
    );
  }

  /**
   * @param Node $node
   * @return JsonResponse
   */
  public function getData(Node $node) {
    $quiz_questions = $node->get('field_questions')->referencedEntities();
    foreach ($quiz_questions as $qkey => $question) {
      $data['quiz']['questions'][$qkey]['question_feedback'] = $question->get('field_mcq_feedback')->getValue()[0]['value'];
      $data['quiz']['questions'][$qkey]['question_title'] = $question->get('field_mcq_question')->getValue()[0]['value'];
      $answers = $question->get('field_mcq_answer')->referencedEntities();
      foreach ($answers as $akey => $answer) {
        $data['quiz']['questions'][$qkey]['answers'][$akey]['choice'] = $answer->get('field_mcq_choice')->getValue()[0]['value'];
        $data['quiz']['questions'][$qkey]['answers'][$akey]['correct'] = $answer->get('field_mcq_correct')->getValue()[0]['value'];
        $data['quiz']['questions'][$qkey]['answers'][$akey]['points'] = $answer->get('field_mcq_points')->getValue()[0]['value'];
      }
    }
    return new JsonResponse($data);
  }

  /**
   * @return JsonResponse
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function storeScore() {
    $content = Json::decode($this->requestStack->getCurrentRequest()->getContent());
    if (!empty($content)) {
      $user_id = $this->currentUser->id();
      $quiz_id = $content['nid'];
      $score = $content['totalScore'];
      $time_spent = $content['timeSpent'];

      $user_quiz_score_storage = $this->entityTypeManager->getStorage('user_quiz_score');
      $old_user_quizzes = $user_quiz_score_storage->loadByProperties([
        'name' => $user_id . '-' . $quiz_id,
      ]);

      if (!empty($old_user_quizzes)) {
        foreach ($old_user_quizzes as $old_user_quiz) {
          $old_user_quiz
            ->set('field_student_score', $score)
            ->set('field_time_sec', $time_spent)
            ->save();
        }
        return new JsonResponse(['message' => 'Data  is updated successfully']);
      }
      else {
        $user_quiz_score = [
          'name' => $user_id . '-' . $quiz_id,
          'field_student' => $user_id,
          'field_quiz' => $quiz_id,
          'field_student_score' => $score,
          'field_time_sec' => $time_spent
        ];
        $user_quiz_score_storage->create($user_quiz_score)
          ->save();
        return new JsonResponse(['message' => 'Data  is inserted successfully']);
      }
    }
    else {
      return new JsonResponse(['message' => 'Error in data insertion/updation.']);
    }
  }
}
