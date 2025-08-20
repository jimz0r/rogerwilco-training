<?php

namespace Drupal\rw_training\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\node\NodeInterface;

class AdminRecalcController extends ControllerBase {
  use MessengerTrait;

  public function recalc() {
    $storage = $this->entityTypeManager()->getStorage('rw_enrollment');
    $ids = $storage->getQuery()->accessCheck(FALSE)->execute();
    if (!$ids) {
      $this->messenger()->addStatus($this->t('No enrollments found.'));
      return $this->redirect('entity.rw_enrollment.collection');
    }

    /** @var \Drupal\rw_training\Service\ProgressCalculator $calc */
    $calc = $this->container()->get('rw_training.progress_calculator');

    $updated = 0;
    foreach ($storage->loadMultiple($ids) as $enrollment) {
      $course = $enrollment->get('course')->entity;
      if ($course instanceof NodeInterface && $course->bundle() === 'course') {
        $calc->recalculateAndSaveProgress($enrollment, $course);
        $updated++;
      }
    }

    $this->messenger()->addStatus($this->t('Recalculated @count enrollment(s).', ['@count' => $updated]));
    return $this->redirect('entity.rw_enrollment.collection');
  }
}
