<?php

namespace Drupal\rw_training\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Computes and stores course progress for a user's Enrollment.
 *
 * Assumptions (adjust if your field names differ):
 * - Content types:
 *   - Course  (bundle: "course")
 *   - Module  (bundle: "module")
 *   - Lesson  (bundle: "lesson")
 * - Relationships:
 *   - Lesson → Course, via entity reference field "field_course_ref"
 *     OR
 *   - Lesson → Module (field "field_module_ref"), and Module → Course (field "field_course_ref")
 *
 * Storage of lesson completion:
 * - Uses KeyValue store collection "rw_training.completions", keyed by Enrollment ID.
 *   Each value is an array like: [ <lesson_nid> => <timestamp>, ... ].
 *
 * Enrollment entity:
 * - ID: "rw_enrollment" with fields:
 *   - user_id (uid), course (ER to node), status (list), percent_complete (decimal),
 *     score (decimal), completed (timestamp).
 */
class ProgressCalculator {

  // If your field machine names are different, change these constants.
  private const FIELD_LESSON_COURSE = 'field_course_ref';
  private const FIELD_LESSON_MODULE = 'field_module_ref';
  private const FIELD_MODULE_COURSE = 'field_course_ref';

  public function __construct(
    private readonly EntityTypeManagerInterface $etm,
  ) {}

  /**
   * Mark a lesson complete for the current user and update enrollment percent.
   */
  public function completeLesson(AccountInterface $account, NodeInterface $lesson): void {
    $course = $this->resolveCourseFromLesson($lesson);
    if (!$course) {
      // No-op if we can't resolve a course; authoring mis-config.
      return;
    }

    $enrollment = $this->getOrCreateEnrollment($account, $course);

    // Record completion in KeyValue.
    $kvs = \Drupal::keyValue('rw_training.completions');
    $completed = $kvs->get($enrollment->id(), []);
    $completed[$lesson->id()] = \Drupal::time()->getRequestTime();
    $kvs->set($enrollment->id(), $completed);

    // Recalculate percent and update enrollment entity.
    $this->recalculateAndSaveProgress($enrollment, $course);
  }

  /**
   * Unmark a lesson complete for the current user and update percent.
   */
  public function uncompleteLesson(AccountInterface $account, NodeInterface $lesson): void {
    $course = $this->resolveCourseFromLesson($lesson);
    if (!$course) {
      return;
    }

    $enrollment = $this->loadEnrollment($account, $course);
    if (!$enrollment) {
      return;
    }

    $kvs = \Drupal::keyValue('rw_training.completions');
    $completed = $kvs->get($enrollment->id(), []);
    if (isset($completed[$lesson->id()])) {
      unset($completed[$lesson->id()]);
      $kvs->set($enrollment->id(), $completed);
      $this->recalculateAndSaveProgress($enrollment, $course);
    }
  }

  /**
   * Check if a lesson is completed by a user.
   */
  public function isLessonCompleted(AccountInterface $account, NodeInterface $lesson): bool {
    $course = $this->resolveCourseFromLesson($lesson);
    if (!$course) {
      return FALSE;
    }
    $enrollment = $this->loadEnrollment($account, $course);
    if (!$enrollment) {
      return FALSE;
    }
    $kvs = \Drupal::keyValue('rw_training.completions');
    $completed = $kvs->get($enrollment->id(), []);
    return array_key_exists($lesson->id(), $completed);
  }

  /**
   * Calculate progress percent (0..100) for a user in a course.
   */
  public function getProgressPercent(AccountInterface $account, NodeInterface $course): float {
    $enrollment = $this->loadEnrollment($account, $course);
    if (!$enrollment) {
      return 0.0;
    }
    $lessons = $this->getLessonIdsForCourse($course);
    if (count($lessons) === 0) {
      return 0.0;
    }
    $kvs = \Drupal::keyValue('rw_training.completions');
    $completed = $kvs->get($enrollment->id(), []);
    $done = count(array_intersect(array_keys($completed), $lessons));
    return round(($done / count($lessons)) * 100, 2);
  }

  /**
   * Resolve the Course node for a given Lesson node via supported relationships.
   */
  public function resolveCourseFromLesson(NodeInterface $lesson): ?NodeInterface {
    if ($lesson->bundle() !== 'lesson') {
      return NULL;
    }

    // Pattern 1: lesson -> course directly.
    if ($lesson->hasField(self::FIELD_LESSON_COURSE) && !$lesson->get(self::FIELD_LESSON_COURSE)->isEmpty()) {
      $course = $lesson->get(self::FIELD_LESSON_COURSE)->entity;
      return ($course instanceof NodeInterface && $course->bundle() === 'course') ? $course : NULL;
    }

    // Pattern 2: lesson -> module -> course.
    if ($lesson->hasField(self::FIELD_LESSON_MODULE) && !$lesson->get(self::FIELD_LESSON_MODULE)->isEmpty()) {
      $module = $lesson->get(self::FIELD_LESSON_MODULE)->entity;
      if ($module instanceof NodeInterface && $module->bundle() === 'module') {
        if ($module->hasField(self::FIELD_MODULE_COURSE) && !$module->get(self::FIELD_MODULE_COURSE)->isEmpty()) {
          $course = $module->get(self::FIELD_MODULE_COURSE)->entity;
          return ($course instanceof NodeInterface && $course->bundle() === 'course') ? $course : NULL;
        }
      }
    }

    return NULL;
  }

  /**
   * Return lesson node IDs belonging to a Course.
   */
  public function getLessonIdsForCourse(NodeInterface $course): array {
    if ($course->bundle() !== 'course') {
      return [];
    }

    $query = $this->etm->getStorage('node')->getQuery()->accessCheck(TRUE)->condition('status', 1);

    // Prefer direct pattern: lesson.field_course_ref == course.id
    $has_direct = $this->bundleHasField('lesson', self::FIELD_LESSON_COURSE);
    if ($has_direct) {
      $ids = $query
        ->condition('type', 'lesson')
        ->condition(self::FIELD_LESSON_COURSE, $course->id())
        ->sort('nid', 'ASC')
        ->execute();
      return array_values($ids);
    }

    // Fallback pattern: lesson.field_module IN (modules referencing course)
    $module_ids = [];
    if ($this->bundleHasField('module', self::FIELD_MODULE_COURSE)) {
      $module_ids = $this->etm->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('type', 'module')
        ->condition(self::FIELD_MODULE_COURSE, $course->id())
        ->execute();
    }
    if ($module_ids && $this->bundleHasField('lesson', self::FIELD_LESSON_MODULE)) {
      $ids = $this->etm->getStorage('node')->getQuery()
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('type', 'lesson')
        ->condition(self::FIELD_LESSON_MODULE, array_values($module_ids), 'IN')
        ->sort('nid', 'ASC')
        ->execute();
      return array_values($ids);
    }

    return [];
  }

  /**
   * Load existing enrollment or NULL.
   */
  public function loadEnrollment(AccountInterface $account, NodeInterface $course) {
    $storage = $this->etm->getStorage('rw_enrollment');
    $existing = $storage->loadByProperties([
      'user_id' => $account->id(),
      'course' => $course->id(),
    ]);
    return $existing ? reset($existing) : NULL;
  }

  /**
   * Load or create an enrollment (defaults to "enrolled").
   */
  public function getOrCreateEnrollment(AccountInterface $account, NodeInterface $course) {
    $enrollment = $this->loadEnrollment($account, $course);
    if ($enrollment) {
      return $enrollment;
    }
    /** @var \Drupal\Core\Entity\EntityInterface $enrollment */
    $enrollment = $this->etm->getStorage('rw_enrollment')->create([
      'user_id' => $account->id(),
      'course' => $course->id(),
      'status' => 'enrolled',
      'percent_complete' => '0.00',
    ]);
    $enrollment->save();
    return $enrollment;
  }

  /**
   * Recalculate percent, update status/completed timestamp, and save enrollment.
   */
  public function recalculateAndSaveProgress($enrollment, NodeInterface $course): void {
    $lessons = $this->getLessonIdsForCourse($course);
    $total = count($lessons);
    $percent = 0.0;

    if ($total > 0) {
      $kvs = \Drupal::keyValue('rw_training.completions');
      $completed = $kvs->get($enrollment->id(), []);
      $done = count(array_intersect(array_keys($completed), $lessons));
      $percent = round(($done / $total) * 100, 2);
    }

    $enrollment->set('percent_complete', (string) $percent);

    if ($percent >= 100.0) {
      $enrollment->set('status', 'completed');
      $enrollment->set('completed', \Drupal::time()->getRequestTime());
    }
    elseif ($percent > 0.0) {
      $enrollment->set('status', 'in_progress');
      $enrollment->set('completed', NULL);
    }
    else {
      $enrollment->set('status', 'enrolled');
      $enrollment->set('completed', NULL);
    }

    $enrollment->save();
  }

  /**
   * Utility: check if a node bundle has a given base/field name.
   */
  private function bundleHasField(string $bundle, string $field): bool {
    $definitions = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $bundle);
    return isset($definitions[$field]);
  }

    /**
   * Return [done, total] lesson counts for a user's course progress.
   */
  public function getProgressCounts(AccountInterface $account, NodeInterface $course): array {
    $lessons = $this->getLessonIdsForCourse($course);
    if (!$lessons) {
      return [0, 0];
    }
    $enrollment = $this->loadEnrollment($account, $course);
    if (!$enrollment) {
      return [0, count($lessons)];
    }
    $kvs = \Drupal::keyValue('rw_training.completions');
    $completed = $kvs->get($enrollment->id(), []);
    $done = count(array_intersect(array_keys($completed), $lessons));
    return [$done, count($lessons)];
  }

  /**
   * Find the next incomplete lesson for a user in a course (or NULL if none).
   */
  public function getNextIncompleteLesson(AccountInterface $account, NodeInterface $course): ?NodeInterface {
    $ids = $this->getLessonIdsForCourse($course);
    if (!$ids) {
      return NULL;
    }
    $enrollment = $this->loadEnrollment($account, $course);
    if (!$enrollment) {
      // Not enrolled yet -> first lesson is the "next".
      $first = reset($ids);
      return $first ? $this->etm->getStorage('node')->load($first) : NULL;
    }
    $kvs = \Drupal::keyValue('rw_training.completions');
    $completed = array_keys($kvs->get($enrollment->id(), []));
    foreach ($ids as $nid) {
      if (!in_array($nid, $completed, true)) {
        return $this->etm->getStorage('node')->load($nid);
      }
    }
    return NULL; // All done.
  }


}
