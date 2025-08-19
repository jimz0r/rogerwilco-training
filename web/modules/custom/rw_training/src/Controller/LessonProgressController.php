<?php

namespace Drupal\rw_training\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Controller to mark lesson completion/uncompletion.
 */
class LessonProgressController extends ControllerBase {
  use MessengerTrait;

  /**
   * Mark the given lesson complete for the current user.
   */
  public function complete(NodeInterface $node): RedirectResponse {
    $account = $this->currentUser();

    // Force login if anonymous.
    if ($account->isAnonymous()) {
      $dest = ['destination' => $node->toUrl()->toString()];
      return $this->redirect('user.login', [], ['query' => $dest]);
    }

    if ($node->bundle() !== 'lesson') {
      $this->messenger()->addError($this->t('This action is only available on Lesson pages.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    /** @var \Drupal\rw_training\Service\ProgressCalculator $calc */
    $calc = \Drupal::service('rw_training.progress_calculator');
    $calc->completeLesson($account, $node);

    $this->messenger()->addStatus($this->t('Marked this lesson as complete.'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

  /**
   * Unmark the given lesson for the current user.
   */
  public function uncomplete(NodeInterface $node): RedirectResponse {
    $account = $this->currentUser();

    if ($account->isAnonymous()) {
      $dest = ['destination' => $node->toUrl()->toString()];
      return $this->redirect('user.login', [], ['query' => $dest]);
    }

    if ($node->bundle() !== 'lesson') {
      $this->messenger()->addError($this->t('This action is only available on Lesson pages.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    /** @var \Drupal\rw_training\Service\ProgressCalculator $calc */
    $calc = \Drupal::service('rw_training.progress_calculator');
    $calc->uncompleteLesson($account, $node);

    $this->messenger()->addStatus($this->t('Marked this lesson as not complete.'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

}
