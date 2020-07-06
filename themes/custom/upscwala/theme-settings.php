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

  $form['upscwala']['bg_image_path'] = [
    '#type' => 'textfield',
    '#title' => t('Background Image Path'),
    '#maxlength' => 255,
    '#description' => t("Use this field to add Background image Path."),
    '#default_value' => theme_get_setting('bg_image_path'),
  ];

}
