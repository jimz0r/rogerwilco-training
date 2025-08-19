<?php

namespace Drupal\rw_training\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects to the next incomplete lesson for the current user.
 */
class NextLessonController extends ControllerBase {
  use MessengerTrait;

  public function go(NodeInterface $node): RedirectResponse {
    if ($node->bundle() !== 'course') {
      $this->messenger()->addError($this->t('Continue is only available on Course pages.'));
      return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
    }

    $account = $this->currentUser();
    if ($account->isAnonymous()) {
      return $this->redirect('user.login', [], ['query' => ['destination' => $node->toUrl()->toString()]]);
    }

    /** @var \Drupal\rw_training\Service\ProgressCalculator $calc */
    $calc = \Drupal::service('rw_training.progress_calculator');
    $next = $calc->getNextIncompleteLesson($account, $node);

    if ($next) {
      return $this->redirect('entity.node.canonical', ['node' => $next->id()]);
    }

    $this->messenger()->addStatus($this->t('You’ve completed all lessons in this course. 🎉'));
    return $this->redirect('entity.node.canonical', ['node' => $node->id()]);
  }

}
