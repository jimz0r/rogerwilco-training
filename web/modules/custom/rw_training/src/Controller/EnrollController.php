<?php

namespace Drupal\rw_training\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Simple enroll/unenroll actions for Courses.
 */
class EnrollController extends ControllerBase {
  use MessengerTrait;

  /**
   * Enroll the current user on a Course node.
   */
  public function enroll(NodeInterface $node): RedirectResponse {
    // Optional guard: only allow enroll on "course" bundle.
    if ($node->bundle() !== 'course') {
      $this->messenger()->addError($this->t('You can only enroll on Course content.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    $account = $this->currentUser();
    $storage = $this->entityTypeManager()->getStorage('rw_enrollment');

    // Check if the user is already enrolled.
    $existing = $storage->loadByProperties([
      'user_id' => $account->id(),
      'course' => $node->id(),
    ]);

    if ($existing) {
      $this->messenger()->addStatus($this->t('You are already enrolled.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    // Create an enrollment record.
    /** @var \Drupal\Core\Entity\EntityInterface $enrollment */
    $enrollment = $storage->create([
      'user_id' => $account->id(),
      'course' => $node->id(),
      'status' => 'enrolled',
    ]);
    $enrollment->save();

    $this->messenger()->addStatus($this->t('Enrolled successfully.'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

  /**
   * Unenroll the current user from a Course node.
   */
  public function unenroll(NodeInterface $node): RedirectResponse {
    $account = $this->currentUser();
    $storage = $this->entityTypeManager()->getStorage('rw_enrollment');

    $existing = $storage->loadByProperties([
      'user_id' => $account->id(),
      'course' => $node->id(),
    ]);

    if ($existing) {
      $enrollment = reset($existing);
      $enrollment->delete();
      $this->messenger()->addStatus($this->t('Unenrolled.'));
    }
    else {
      $this->messenger()->addWarning($this->t('You are not enrolled on this course.'));
    }

    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

}
