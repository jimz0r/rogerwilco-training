<?php

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\NodeType;

/**
 * Create Course, Module, Lesson bundles and required fields.
 */
function rw_training_post_update_create_training_bundles(&$sandbox = NULL) {
  // Helper closures.
  $ensure_bundle = function (string $type, string $label, ?string $description = NULL) {
    if (!NodeType::load($type)) {
      $t = NodeType::create([
        'type' => $type,
        'name' => $label,
        'description' => $description ?? '',
        'new_revision' => TRUE,
      ]);
      $t->save();
    }
  };

  $ensure_field_storage = function (string $entity_type, string $field_name, array $storage_def) {
    if (!FieldStorageConfig::loadByName($entity_type, $field_name)) {
      $storage = FieldStorageConfig::create([
        'entity_type' => $entity_type,
        'field_name' => $field_name,
      ] + $storage_def);
      $storage->save();
    }
  };

  $ensure_field_instance = function (string $entity_type, string $bundle, string $field_name, array $field_def) {
    if (!FieldConfig::loadByName($entity_type, $bundle, $field_name)) {
      $field = FieldConfig::create([
        'entity_type' => $entity_type,
        'bundle' => $bundle,
        'field_name' => $field_name,
      ] + $field_def);
      $field->save();
    }
  };

  $ensure_form_display_widget = function (string $entity_type, string $bundle, string $mode, string $field_name, string $type, array $settings = [], int $weight = 0) {
    $fd = EntityFormDisplay::load("$entity_type.$bundle.$mode") ?: EntityFormDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => $mode,
      'status' => TRUE,
    ]);
    $fd->setComponent($field_name, ['type' => $type, 'weight' => $weight, 'settings' => $settings] + ['region' => 'content']);
    $fd->save();
  };

  $ensure_view_display_formatter = function (string $entity_type, string $bundle, string $mode, string $field_name, string $type, array $settings = [], int $weight = 0) {
    $vd = EntityViewDisplay::load("$entity_type.$bundle.$mode") ?: EntityViewDisplay::create([
      'targetEntityType' => $entity_type,
      'bundle' => $bundle,
      'mode' => $mode,
      'status' => TRUE,
    ]);
    $vd->setComponent($field_name, ['type' => $type, 'weight' => $weight, 'settings' => $settings] + ['region' => 'content']);
    $vd->save();
  };

  // 1) Bundles.
  $ensure_bundle('course', 'Course', 'A training course (top-level).');
  $ensure_bundle('module', 'Module', 'An optional grouping of lessons inside a course.');
  $ensure_bundle('lesson', 'Lesson', 'A single learning unit inside a course.');

  // 2) Field storages.
  // lesson.field_course_ref → ER to node:course
  $ensure_field_storage('node', 'field_course_ref', [
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'node'],
  ]);

  // module.field_course_ref → ER to node:course
  $ensure_field_storage('node', 'field_course_ref', [
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'node'],
  ]);

  // lesson.field_module → ER to node:module (optional)
  $ensure_field_storage('node', 'field_module_ref', [
    'type' => 'entity_reference',
    'cardinality' => 1,
    'settings' => ['target_type' => 'node'],
  ]);

  // 3) Field instances.
  // Module → Course.
  $ensure_field_instance('node', 'module', 'field_course_ref', [
    'label' => 'Course',
    'required' => TRUE,
    'settings' => [
      'handler' => 'default',
      'handler_settings' => ['target_bundles' => ['course' => 'course']],
    ],
  ]);

  // Lesson → Course (direct mapping; recommended).
  $ensure_field_instance('node', 'lesson', 'field_course_ref', [
    'label' => 'Course',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default',
      'handler_settings' => ['target_bundles' => ['course' => 'course']],
    ],
  ]);

  // Lesson → Module (optional).
  $ensure_field_instance('node', 'lesson', 'field_module_ref', [
    'label' => 'Module',
    'required' => FALSE,
    'settings' => [
      'handler' => 'default',
      'handler_settings' => ['target_bundles' => ['module' => 'module']],
    ],
  ]);

  // 4) Form displays.
  $ensure_form_display_widget('node', 'module', 'default', 'field_course_ref', 'entity_reference_autocomplete', [], 10);
  $ensure_form_display_widget('node', 'lesson', 'default', 'field_course_ref', 'entity_reference_autocomplete', [], 10);
  $ensure_form_display_widget('node', 'lesson', 'default', 'field_module_ref', 'entity_reference_autocomplete', [], 11);

  // 5) View displays.
  $ensure_view_display_formatter('node', 'module', 'default', 'field_course_ref', 'entity_reference_label', ['link' => TRUE], 10);
  $ensure_view_display_formatter('node', 'lesson', 'default', 'field_course_ref', 'entity_reference_label', ['link' => TRUE], 10);
  $ensure_view_display_formatter('node', 'lesson', 'default', 'field_module_ref', 'entity_reference_label', ['link' => TRUE], 11);

  // All done.
}
