<?php

/**
 * @file
 * Scale classes.
 *
 * Question type, enabling rapid creation of simple surveys using the quiz
 * framework.
 *
 * @todo: We mix the names answer_collection and alternatives. Use either alternative or answer consistently
 */

/**
 * Extension of QuizQuestion.
 */
class ScaleQuestion extends QuizQuestion {

  // $util will be set to true if an instance of this class is used only as a
  // utility.
  protected $util = FALSE;
  // (answer)Collection id.
  protected $col_id = NULL;

  /**
   * Tells the instance that it is being used as a utility.
   *
   * @param int $col_id
   *   Answer collection id.
   */
  public function initUtil($col_id) {
    $this->util = TRUE;
    $this->col_id = $col_id;
  }

  /**
   * Implementation of saveNodeProperties().
   *
   * @see QuizQuestion::saveNodeProperties()
   */
  public function saveNodeProperties($is_new = FALSE) {
    $is_new_node = $is_new || $this->node->revision == 1;
    $answer_collection_id = $this->saveAnswerCollection($is_new_node);
    // Save the answer collection as a preset if the save preset option is
    // checked.
    if (!empty($this->node->save)) {
      $this->setPreset($answer_collection_id);
    }
    if ($is_new_node) {
      $id = db_insert('quiz_scale_node_properties')
        ->fields(array(
          'nid' => $this->node->nid,
          'vid' => $this->node->vid,
          'answer_collection_id' => $answer_collection_id,
        ))
        ->execute();
    }
    else {
      db_update('quiz_scale_node_properties')
        ->fields(array(
          'answer_collection_id' => $answer_collection_id,
        ))
        ->condition('nid', $this->node->nid)
        ->condition('vid', $this->node->vid)
        ->execute();
    }
  }

  /**
   * Add a preset for the current user.
   *
   * @param int $col_id
   *   Answer collection id of the collection this user wants to have as a
   *   preset.
   */
  private function setPreset($col_id) {
    db_merge('quiz_scale_user')
      ->key(array(
        'uid' => $GLOBALS['user']->uid,
        'answer_collection_id' => $col_id,
      ))
      ->fields(array(
        'uid' => $GLOBALS['user']->uid,
        'answer_collection_id' => $col_id,
      ))
      ->execute();
  }

  /**
   * Stores|Identifies the answer collection.
   *
   * Stores the answer collection to the database, or identifies an existing
   * collection. We try to reuse answer collections as much as possible to
   * minimize the amount of rows in the database, and thereby improving
   * performance when surveys are being taken.
   *
   * @param bool $is_new_node
   *   TRUE if the question is being inserted, or FALSE for update.
   * @param array $alt_input
   *   The alternatives array to be saved.
   * @param int $preset
   *   Use 1 for preset, 0 for not a preset.
   *
   * @return int
   *   Answer collection id
   */
  public function saveAnswerCollection($is_new_node, array $alt_input = NULL, $preset = NULL) {
    $user = \Drupal::currentUser();
    if (!isset($alt_input)) {
      $alt_input = get_object_vars($this->node);
    }
    if (!isset($preset) && isset($this->node->save)) {
      $preset = $this->node->save;
    }

    $alternatives = array();
    for ($i = 0; $i < variable_get('scale_max_num_of_alts', 10); $i++) {
      if (isset($alt_input['alternative' . $i]) && drupal_strlen($alt_input['alternative' . $i]) > 0) {
        // Handle the form.
        $alternatives[] = $alt_input['alternative' . $i];
      }
      elseif (isset($alt_input['scale'][$i]->answer) && drupal_strlen($alt_input['scale'][$i]->answer) > 0) {
        // Handle programmatic save.
        $alternatives[] = $alt_input['scale'][$i]->answer;
      }
    }

    // If an identical answer collection already exists.
    if ($answer_collection_id = $this->existingCollection($alternatives)) {
      if ($preset == 1) {
        $this->setPreset($answer_collection_id);
      }
      if (!$is_new_node || $this->util) {
        $col_to_delete = $this->util ? $this->col_id : $this->node->scale[0]->answer_collection_id;
        if ($col_to_delete != $answer_collection_id) {
          // We try to delete the old answer collection.
          $this->deleteCollectionIfNotUsed($col_to_delete, 1);
        }
      }
      return $answer_collection_id;
    }
    // Register a new answer collection.
    $answer_collection_id = db_insert('quiz_scale_answer_collection')
      ->fields(array('for_all' => 1))
      ->execute();

    // Save as preset if checkbox for preset has been checked.
    if ($preset == 1) {
      $id = db_insert('quiz_scale_user')
        ->fields(array(
          'uid' => $user->id(),
          'answer_collection_id' => $answer_collection_id,
        ))
        ->execute();
    }
    // Save the alternatives in the answer collection.
    //db_lock_table('quiz_scale_answer');
    for ($i = 0; $i < count($alternatives); $i++) {
      $this->saveAlternative($alternatives[$i], $answer_collection_id);
    }
    //db_unlock_tables();
    return $answer_collection_id;
  }

  /**
   * Saves one alternative to the database.
   *
   * @param string $alternative
   *   The alternative to be saved.
   * @param int $answer_collection_id
   *   The id of the answer collection this alternative shall belong to.
   */
  private function saveAlternative($alternative, $answer_collection_id) {
    $id = db_insert('quiz_scale_answer')
      ->fields(array(
        'answer_collection_id' => $answer_collection_id,
        'answer' => $alternative,
      ))
      ->execute();
  }

  /**
   * Deletes an answer collection if it isn't being used.
   *
   * @param int $answer_collection_id
   *   Answer collection id.
   * @param int $accept
   *   If collection is used more than this many times we keep it.
   *
   * @return bool
   *   TRUE if deleted, FALSE if not deleted.
   */
  public function deleteCollectionIfNotUsed($answer_collection_id, $accept = 0) {
    // Check if the collection is someones preset. If it is we can't delete it.
    $count = db_query('SELECT COUNT(*) FROM {quiz_scale_user} WHERE answer_collection_id = :acid', array(':acid' => $answer_collection_id))->fetchField();
    if ($count > 0) {
      return FALSE;
    }

    // Check if the collection is a global preset. If it is we can't delete it.
    $for_all = db_query('SELECT for_all FROM {quiz_scale_answer_collection} WHERE id = :id', array(':id' => $answer_collection_id))->fetchField();
    if ($for_all == 1) {
      return FALSE;
    }

    // Check if the collection is used in an existing question. If it is we
    // can't delete it.
    $count = db_query('SELECT COUNT(*) FROM {quiz_scale_node_properties} WHERE answer_collection_id = :acid', array(':acid' => $answer_collection_id))->fetchField();

    // We delete the answer collection if it isn't being used by enough
    // questions.
    if ($count <= $accept) {
      db_delete('quiz_scale_answer_collection')
        ->condition('id', $answer_collection_id)
        ->execute();

      db_delete('quiz_scale_answer')
        ->condition('answer_collection_id', $answer_collection_id)
        ->execute();
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Finds out if a collection already exists.
   *
   * @param array $alternatives
   *   This is the collection that will be compared with the database.
   * @param int $answer_collection_id
   *   If we are matching a set of alternatives with a given collection that
   *   exists in the database.
   * @param int $last_id
   *   The id of the last alternative we compared with.
   *
   * @return bool
   *   TRUE if the collection exists, FALSE otherwise.
   */
  private function existingCollection(array $alternatives, $answer_collection_id = NULL, $last_id = NULL) {
    $my_alts = isset($answer_collection_id) ? $alternatives : array_reverse($alternatives);

    // Find all answers identical to the next answer in $alternatives.
    $sql = 'SELECT id, answer_collection_id FROM {quiz_scale_answer} WHERE answer = :answer';
    $args[':answer'] = array_pop($my_alts);
    // Filter on collection id.
    if (isset($answer_collection_id)) {
      $sql .= ' AND answer_collection_id = :acid';
      $args[':acid'] = $answer_collection_id;
    }
    // Filter on alternative id (If we are investigating a specific
    // collection, the alternatives needs to be in a correct order).
    if (isset($last_id)) {
      $sql .= ' AND id = :id';
      $args[':id'] = $last_id + 1;
    }
    $res = db_query($sql, $args);
    if (!$res_o = $res->fetch()) {
      return FALSE;
    }

    /*
     * If all alternatives has matched make sure the collection we are
     * comparing against in the database doesn't have more alternatives.
     */
    if (count($my_alts) == 0) {
      $res_o2 = db_query('SELECT * FROM {quiz_scale_answer}
              WHERE answer_collection_id = :answer_collection_id
              AND id = :id', array(':answer_collection_id' => $answer_collection_id, ':id' => ($last_id + 2)))->fetch();
      return ($res_o2) ? FALSE : $answer_collection_id;
    }

    // Do a recursive call to this function on all answer collection candidates.
    do {
      $col_id = $this->existingCollection($my_alts, $res_o->answer_collection_id, $res_o->id);
      if ($col_id) {
        return $col_id;
      }
    } while ($res_o = $res->fetch());
    return FALSE;
  }

  /**
   * Implementation of validateNode().
   *
   * @see QuizQuestion::validateNode()
   */
  public function validateNode(array &$form) {

  }

  /**
   * Implementation of delete().
   *
   * @see QuizQuestion::delete()
   */
  public function delete($only_this_version = FALSE) {
    parent::delete($only_this_version);
    if ($only_this_version) {

      db_delete('quiz_scale_node_properties')
        ->condition('nid', $this->node->nid)
        ->condition('vid', $this->node->vid)
        ->execute();
    }
    else {

      db_delete('quiz_scale_node_properties')
        ->condition('nid', $this->node->nid)
        ->execute();
    }
    $this->deleteCollectionIfNotUsed($this->node->scale[0]->answer_collection_id, 0);
  }

  /**
   * Implementation of getNodeProperties().
   *
   * @see QuizQuestion::getNodeProperties()
   */
  public function getNodeProperties() {
    if (isset($this->nodeProperties)) {
      return $this->nodeProperties;
    }
    $props = parent::getNodeProperties();

    $res = db_query('SELECT id, answer, a.answer_collection_id
            FROM {quiz_scale_node_properties} p
            JOIN {quiz_scale_answer} a ON (p.answer_collection_id = a.answer_collection_id)
            WHERE nid = :nid AND vid = :vid
            ORDER BY a.id', array(':nid' => $this->node->nid, ':vid' => $this->node->vid));
    foreach ($res as $res_o) {
      $props['scale'][] = $res_o;
    }
    $this->nodeProperties = $props;
    return $props;
  }

  /**
   * Implementation of getNodeView().
   *
   * @see QuizQuestion::getNodeView()
   */
  public function getNodeView() {
    $content = parent::getNodeView();
    $alternatives = array();
    for ($i = 0; $i < variable_get('scale_max_num_of_alts', 10); $i++) {
      if (isset($this->node->scale[$i]->answer) && drupal_strlen($this->node->scale[$i]->answer) > 0) {
        $alternatives[] = check_plain($this->node->scale[$i]->answer);
      }
    }
    $content['answers'] = array(
      '#markup' => theme('scale_answer_node_view', array('alternatives' => $alternatives)),
      '#weight' => 2,
    );
    return $content;
  }

  /**
   * Implementation of getAnsweringForm().
   *
   * @see QuizQuestion::getAnsweringForm()
   */
  public function getAnsweringForm(array $form_state = NULL, $result_id) {
    $form = parent::getAnsweringForm($form_state, $result_id);
    //$form['#theme'] = 'scale_answering_form';
    $options = array();
    for ($i = 0; $i < variable_get('scale_max_num_of_alts', 10); $i++) {
      if (isset($this->node->scale[$i]) && drupal_strlen($this->node->scale[$i]->answer) > 0) {
        $options[$this->node->scale[$i]->id] = check_plain($this->node->scale[$i]->answer);
      }
    }

    $form = array(
      '#type' => 'radios',
      '#title' => t('Choose one'),
      '#options' => $options,
    );
    if (isset($result_id)) {
      $response = new ScaleResponse($result_id, $this->node);
      $form['#default_value'] = $response->getResponse();
    }
    return $form;
  }

  /**
   * Question response validator.
   */
  public function getAnsweringFormValidate(array &$element, &$value) {
    if ($value == '') {
      form_error($element, t('You must provide an answer.'));
    }
  }

  /**
   * Implementation of getCreationForm().
   *
   * @see QuizQuestion::getCreationForm()
   */
  public function getCreationForm(array &$form_state = NULL) {
    $form = array();

    // Getting presets from the database.
    $collections = $this->getPresetCollections(TRUE);
    $options = $this->makeOptions($collections);
    $options['d'] = '-'; // Default.
    $jsArray = $this->makeJSArray($collections);

    $form['answer'] = array(
      '#type' => 'fieldset',
      '#title' => t('Answer'),
      '#description' => t('Provide alternatives for the user to answer.'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
      '#weight' => -4,
    );
    $form['answer']['#theme'][] = 'scale_creation_form';
    $form['answer']['presets'] = array(
      '#type' => 'select',
      '#title' => t('Presets'),
      '#options' => $options,
      '#default_value' => 'd',
      '#description' => t('Select a set of alternatives'),
      '#attributes' => array('onchange' => 'refreshAlternatives(this)'),
    );
    $max_num_alts = variable_get('scale_max_num_of_alts', 10);
    // TODO: use drupal_add_js($path, 'settings').
    $form['jsArray'] = array('#markup' => "<script type='text/javascript'>$jsArray var scale_max_num_of_alts = $max_num_alts;</script>");
    $form['answer']['alternatives'] = array(
      '#type' => 'fieldset',
      '#title' => t('Alternatives'),
      '#collapsible' => TRUE,
      '#collapsed' => TRUE,
    );
    for ($i = 0; $i < $max_num_alts; $i++) {
      $form['answer']['alternatives']["alternative$i"] = array(
        '#type' => 'textfield',
        '#title' => t('Alternative !i', array('!i' => ($i + 1))),
        '#size' => 60,
        '#maxlength' => 256,
        '#default_value' => isset($this->node->scale[$i]->answer) ? $this->node->scale[$i]->answer : '',
        '#required' => $i < 2,
      );
    }
    // @todo: Rename save to save_as_preset or something.
    $form['answer']['alternatives']['save'] = array(
      '#type' => 'checkbox',
      '#title' => t('Save as a new preset'),
      '#description' => t('Current alternatives will be saved as a new preset'),
      '#default_value' => FALSE,
    );
    $form['answer']['manage'] = array('#markup' => l(t('Manage presets'), 'scale/collection/manage'));
    return $form;
  }

  /**
   * Get all available presets for the current user.
   *
   * @param bool $with_defaults
   *
   * @return array
   *   Array holding all the preset collections as an array of objects. Each
   *   object in the array has the following properties:
   *     ->alternatives(array);
   *     ->name(string);
   *     ->for_all(int, 0|1);
   */
  public function getPresetCollections($with_defaults = FALSE) {
    $user = \Drupal::currentUser();

    // Array holding data for each collection.
    $collections = array();
    $scale_element_names = array();
    $sql = 'SELECT DISTINCT ac.id AS answer_collection_id, a.answer, ac.for_all
            FROM {quiz_scale_user} au
            JOIN {quiz_scale_answer_collection} ac ON(au.answer_collection_id = ac.id)
            JOIN {quiz_scale_answer} a ON(a.answer_collection_id = ac.id)
            WHERE au.uid = :uid';
    if ($with_defaults) {
      $sql .= ' OR ac.for_all = 1';
    }
    $sql .= ' ORDER BY au.answer_collection_id, a.id';
    $res = db_query($sql, array(':uid' => $user->id()));
    $col_id = NULL;

    // Populate the $collections array.
    while (TRUE) {
      if (!($res_o = $res->fetch()) || ($res_o->answer_collection_id != $col_id)) {

        // We have gone through all elements for one answer collection, and
        // need to store the answer collection name and id in the options array.
        if (isset($col_id)) {
          $num_scale_elements = count($collections[$col_id]->alternatives);
          $collections[$col_id]->name = check_plain($collections[$col_id]->alternatives[0] . ' - ' . $collections[$col_id]->alternatives[$num_scale_elements - 1] . ' (' . $num_scale_elements . ')');
        }
        // Break the loop if there are no more answer collections to process.
        if (!$res_o) {
          break;
        }

        // Init the next collection in the $collections array.
        $col_id = $res_o->answer_collection_id;
        if (!isset($collections[$col_id])) {
          $collections[$col_id] = new stdClass();
          $collections[$col_id]->alternatives = array();
          $collections[$col_id]->for_all = $res_o->for_all;
        }
      }
      $collections[$col_id]->alternatives[] = check_plain($res_o->answer);
    }
    return $collections;
  }

  /**
   * Makes options array for form elements.
   *
   * @param array $collections
   *   Collections array, from getPresetCollections() for instance...
   *
   * @return array
   *   An array with options.
   */
  private function makeOptions(array $collections = NULL) {
    $options = array();
    foreach ($collections as $col_id => $obj) {
      $options[$col_id] = $obj->name;
    }
    return $options;
  }

  /**
   * Makes a javascript constructing an answer collection array.
   *
   * @param array $collections
   *   Collections array, from getPresetCollections() for instance...
   *
   * @return string
   *   javascript(string)
   */
  private function makeJSArray(array $collections = NULL) {
    $jsArray = 'scaleCollections = new Array();';
    foreach ($collections as $col_id => $obj) {
      if (is_array($collections[$col_id]->alternatives)) {
        $jsArray .= "scaleCollections[$col_id] = new Array();";
        foreach ($collections[$col_id]->alternatives as $alt_id => $text) {
          $jsArray .= "scaleCollections[$col_id][$alt_id] = '" . check_plain($text) . "';";
        }
      }
    }
    return $jsArray;
  }

  /**
   * Implementation of getMaximumScore().
   *
   * @see QuizQuestion::getMaximumScore()
   */
  public function getMaximumScore() {
    // In some use-cases we want to reward users for answering a survey
    // question. This is why 1 is returned and not zero.
    return 1;
  }

}
