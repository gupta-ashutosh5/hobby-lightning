<?php

namespace Drupal\docprops\Plugin\CKEditorPlugin;

use Drupal\ckeditor\CKEditorPluginBase;
use Drupal\editor\Entity\Editor;

/**
 * Defines the "docprops" plugin.
 *
 * @CKEditorPlugin(
 *   id = "docprops",
 *   label = @Translation("CKEditor Document Properties"),
 * )
 */
class DocProps extends CKEditorPluginBase {

  /**
   * Get path to library folder.
   */
  public function getLibraryPath() {
    return 'libraries/docprops';
  }

  /**
   * {@inheritdoc}
   */
  public function getFile() {
    return $this->getLibraryPath() . '/plugin.js';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(Editor $editor) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getButtons() {
    $path = $this->getLibraryPath();
    return [
      'DocProps' => [
        'label' => $this->t('Document Properties'),
        'image' => $path . '/icons/docprops.png',
      ],
    ];
  }

}
