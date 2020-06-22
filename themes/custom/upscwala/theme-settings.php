<?php

/**
 * @file
 * Theme settings form for Upscwala theme.
 */

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function upscwala_form_system_theme_settings_alter(&$form, &$form_state) {

  $form['upscwala'] = [
    '#type' => 'details',
    '#title' => t('Upscwala'),
    '#open' => TRUE,
  ];

  $form['upscwala']['font_size'] = [
    '#type' => 'number',
    '#title' => t('Font size'),
    '#min' => 12,
    '#max' => 18,
    '#default_value' => theme_get_setting('font_size'),
  ];

}
