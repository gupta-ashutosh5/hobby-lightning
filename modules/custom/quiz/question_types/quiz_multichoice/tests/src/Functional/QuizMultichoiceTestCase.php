<?php

namespace Drupal\Tests\quiz_multichoice\Functional;

use Drupal\paragraphs\Entity\Paragraph;
use Drupal\quiz\Entity\QuizQuestion;
use Drupal\Tests\quiz\Functional\QuizQuestionTestBase;

/**
 * Test multiple choice questions.
 *
 * @group Quiz
 */
class QuizMultichoiceTestCase extends QuizQuestionTestBase {

  public static $modules = array('quiz', 'quiz_multichoice');

  /**
   * Create a default MCQ with default settings.
   */
  public function testCreateQuizQuestion($settings = []) {

    // Set up some alternatives.
    $a = Paragraph::create([
        'type' => 'multichoice',
        'multichoice_answer' => 'Alternative A',
        'multichoice_feedback_chosen' => 'You chose A',
        'multichoice_feedback_not_chosen' => 'You did not choose A',
        'multichoice_score_chosen' => 1,
        'multichoice_score_not_chosen' => 0,
    ]);
    $a->save();

    $b = Paragraph::create([
        'type' => 'multichoice',
        'multichoice_answer' => 'Alternative B',
        'multichoice_feedback_chosen' => 'You chose B',
        'multichoice_feedback_not_chosen' => 'You did not choose B',
        'multichoice_score_chosen' => -1,
        'multichoice_score_not_chosen' => 0,
    ]);
    $b->save();

    $c = Paragraph::create([
        'type' => 'multichoice',
        'multichoice_answer' => 'Alternative C',
        'multichoice_feedback_chosen' => 'You chose C',
        'multichoice_feedback_not_chosen' => 'You did not choose C',
        'multichoice_score_chosen' => -1,
        'multichoice_score_not_chosen' => 0,
    ]);
    $c->save();

    $question = QuizQuestion::create($settings + [
        'title' => 'MCQ 1 Title',
        'type' => 'multichoice',
        'choice_multi' => 0,
        'choice_random' => 0,
        'choice_boolean' => 0,
        'body' => 'MCQ 1 body text',
    ]);

    $question->get('alternatives')->appendItem($a);
    $question->get('alternatives')->appendItem($b);
    $question->get('alternatives')->appendItem($c);

    $question->save();

    return $question;
  }

  public function testQuestionFeedback() {
    $this->drupalLogin($this->admin);

    $question = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Test incorrect question. Feedback, answer.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer]" => 2,
      ), t('Finish'));
    $this->assertPattern('/quiz-score-icon selected/', 'Found selected answer.');
    $this->assertPattern('/quiz-score-icon should/', 'Found should answer.');
    $this->assertPattern('/quiz-score-icon incorrect/', 'Found incorrect answer.');
    $this->assertText('You did not choose A');
    $this->assertText('You chose B');
    $this->assertText('You did not choose C');
  }

  /**
   * Test multiple answers.
   */
  public function testMultipleAnswers() {
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(array('choice_multi' => 1));
    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      "question[{$question->id()}][answer][user_answer][3]" => 3,
      ), t('Finish'));
    // 0 of 1, because user picked a correct answer and an incorrect answer.
    $this->assertText('You got 0 of 1 possible points.');
    $this->assertText('Your score: 0%');

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      ), t('Finish'));
    // 1 of 1, because user picked a correct answer and not an incorrect answer.
    $this->assertText('You got 1 of 1 possible points.');
    $this->assertText('Your score: 100%');
  }

  /**
   * Test restoring a multiple choice answer.
   */
  public function testAnswerMultiRestore() {
    // Checkboxes.
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(array('choice_multi' => 1));
    $question2 = $this->testCreateQuizQuestion(array('choice_multi' => 1));
    $quiz = $this->linkQuestionToQuiz($question);
    $this->linkQuestionToQuiz($question2, $quiz);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      ), t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->assertFieldChecked('edit-question-1-answer-user-answer-1');
  }

  /**
   * Test restoring a single choice answer.
   */
  public function testAnswerSingleRestore() {
    // Radio buttons.
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(array('choice_multi' => 0));
    $question2 = $this->testCreateQuizQuestion(array('choice_multi' => 0));
    $quiz = $this->linkQuestionToQuiz($question);
    $this->linkQuestionToQuiz($question2, $quiz);

    $this->drupalLogin($this->user);
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer]" => 1,
      ), t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->assertFieldChecked('edit-question-1-answer-user-answer-1');
  }

  /**
   * Test random order of choices.
   *
   * @todo I don't know how we would test random questions.
   */
  public function testRandomOrder() {
    $this->drupalLogin($this->admin);
    $question = $this->testCreateQuizQuestion(array('choice_random' => 1));
    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer]" => 1,
      ), t('Finish'));
  }

  /**
   * Test simple scoring.
   */
  public function testSimpleScoring() {
    $this->drupalLogin($this->admin);
    $settings = array();
    $settings['alternatives'][1]['score_if_chosen'] = 0;
    $settings['alternatives'][1]['score_if_not_chosen'] = 0;
    $settings['choice_multi'] = 1;
    $settings['choice_boolean'] = 1;

    $question = $this->testCreateQuizQuestion($settings);
    $quiz = $this->linkQuestionToQuiz($question);

    $this->drupalLogin($this->user);

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      "question[{$question->id()}][answer][user_answer][3]" => 3,
      ), t('Finish'));
    $this->assertText('You got 0 of 1 possible points.');
    $this->assertText('Your score: 0%');

    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer][1]" => 1,
      ), t('Finish'));
    $this->assertText('You got 1 of 1 possible points.');
    $this->assertText('Your score: 100%');
  }

  /**
   * Test that the question response can be edited.
   */
  public function testEditQuestionResponse() {
    // Create & link a question.
    $question = $this->testCreateQuizQuestion();
    $quiz = $this->linkQuestionToQuiz($question);

    $question2 = $this->testCreateQuizQuestion();
    $this->linkQuestionToQuiz($question2, $quiz);

    // Login as non-admin.
    $this->drupalLogin($this->user);

    // Take the quiz.
    $this->drupalGet("quiz/{$quiz->id()}/take");

    // Test editing a question.
    $this->drupalGet("quiz/{$quiz->id()}/take");
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer]" => 1,
      ), t('Next'));
    $this->drupalGet("quiz/{$quiz->id()}/take/1");
    $this->drupalPostForm(NULL, array(
      "question[{$question->id()}][answer][user_answer]" => 2,
      ), t('Next'));
  }

  public function getQuestionType() {
    return 'multichoice';
  }

}
