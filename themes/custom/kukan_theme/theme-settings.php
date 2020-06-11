<?php

/**
 * @file
 * Theme settings form for Kukan Theme theme.
 */

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function kukan_theme_form_system_theme_settings_alter(&$form, &$form_state) {

  $form['kukan_theme'] = [
    '#type' => 'details',
    '#title' => t('Kukan Theme'),
    '#open' => TRUE,
  ];

  $form['kukan_theme']['font_size'] = [
    '#type' => 'number',
    '#title' => t('Font size'),
    '#min' => 12,
    '#max' => 18,
    '#default_value' => theme_get_setting('font_size'),
  ];

}
