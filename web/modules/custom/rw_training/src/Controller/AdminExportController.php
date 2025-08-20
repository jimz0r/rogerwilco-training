<?php

namespace Drupal\rw_training\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Returns a CSV export of enrollments (non-streaming).
 */
class AdminExportController extends ControllerBase {

  /**
   * /admin/content/enrollments/export.csv
   */
  public function csv(): Response {
    // Build CSV into a temporary in-memory stream for reliability.
    $handle = fopen('php://temp', 'r+');

    // Optional BOM for Excel compatibility (UTF-8).
    fwrite($handle, "\xEF\xBB\xBF");

    // Header row.
    fputcsv($handle, [
      'Enrollment ID',
      'User ID',
      'User name',
      'User email',
      'Course NID',
      'Course title',
      'Status',
      'Percent complete',
      'Enrolled on',
      'Completed on',
    ]);

    // Services.
    $df = \Drupal::service('date.formatter');
    $etm = $this->entityTypeManager();
    $storage = $etm->getStorage('rw_enrollment');

    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    if ($ids) {
      foreach ($storage->loadMultiple($ids) as $enrollment) {
        $user = $enrollment->get('user_id')->entity;
        $course = $enrollment->get('course')->entity;

        $uid = $user ? $user->id() : '';
        $uname = $user ? $user->label() : '';
        $uemail = ($user && method_exists($user, 'getEmail')) ? (string) $user->getEmail() : '';

        $cid = ($course instanceof NodeInterface) ? $course->id() : '';
        $ctitle = ($course instanceof NodeInterface) ? $course->label() : '';

        $status = (string) ($enrollment->get('status')->value ?? '');
        $percent = (string) ($enrollment->get('percent_complete')->value ?? '0.00');

        $created = (int) ($enrollment->get('created')->value ?? 0);
        $completed = (int) ($enrollment->get('completed')->value ?? 0);

        fputcsv($handle, [
          $enrollment->id(),
          $uid,
          $uname,
          $uemail,
          $cid,
          $ctitle,
          $status,
          $percent,
          $created ? $df->format($created, 'short') : '',
          $completed ? $df->format($completed, 'short') : '',
        ]);
      }
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    $headers = [
      'Content-Type' => 'text/csv; charset=UTF-8',
      'Content-Disposition' => 'attachment; filename="enrollments-' . date('Y-m-d') . '.csv"',
      'Cache-Control' => 'no-store, no-cache, must-revalidate',
      'Pragma' => 'no-cache',
    ];

    return new Response($csv, 200, $headers);
  }

}
