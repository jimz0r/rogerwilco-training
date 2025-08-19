<?php

namespace Drupal\rw_training\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Provides an "Enroll / Unenroll" button for Course nodes.
 *
 * @Block(
 *   id = "rw_training_enroll_button",
 *   admin_label = @Translation("Enroll button (Course)"),
 *   category = @Translation("RW Training")
 * )
 */
class EnrollButtonBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');

    // Route parameter can be an int; normalize to a loaded entity.
    if (is_numeric($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load((int) $node);
    }
    if (!$node instanceof NodeInterface || $node->bundle() !== 'course') {
      return [];
    }

    $account = \Drupal::currentUser();
    if ($account->isAnonymous()) {
      // Simple login link with destination back to this page.
      $login = Link::createFromRoute($this->t('Log in to enroll'), 'user.login', [], [
        'query' => ['destination' => \Drupal::request()->getRequestUri()],
        'attributes' => ['class' => ['button']],
      ])->toRenderable();

      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['rw-enroll-cta']],
        'login' => $login,
        '#cache' => [
          'contexts' => ['route', 'user'],
          'tags' => $node->getCacheTags(),
        ],
      ];
    }

    // Check if current user already has an enrollment.
    $storage = \Drupal::entityTypeManager()->getStorage('rw_enrollment');
    $existing = $storage->loadByProperties([
      'user_id' => $account->id(),
      'course' => $node->id(),
    ]);
    $is_enrolled = !empty($existing);

    $link = $is_enrolled
      ? Link::createFromRoute($this->t('Unenroll'), 'rw_training.unenroll', ['node' => $node->id()], [
          'attributes' => ['class' => ['button', 'button--secondary']],
        ])->toRenderable()
      : Link::createFromRoute($this->t('Enroll'), 'rw_training.enroll', ['node' => $node->id()], [
          'attributes' => ['class' => ['button', 'button--primary']],
        ])->toRenderable();

    // Optional link to admin listing for users with permission.
    $links = [$link];
    if ($account->hasPermission('administer rw training')) {
      $links[] = Link::createFromRoute($this->t('Manage enrollments'), 'entity.rw_enrollment.collection', [], [
        'attributes' => ['class' => ['button', 'button--link']],
      ])->toRenderable();
    }

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['rw-enroll-cta']],
      'actions' => [
        '#theme' => 'item_list',
        '#items' => $links,
        '#attributes' => ['class' => ['rw-enroll-actions']],
      ],
      '#cache' => [
        'contexts' => ['route', 'user'],
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

  /**
   * Only show this block on Course nodes to logged-in users (or anonymous sees login).
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');
    if (is_numeric($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load((int) $node);
    }

    if ($node instanceof NodeInterface && $node->bundle() === 'course') {
      // Allow; cache per route and user so the button switches correctly.
      return AccessResult::allowed()->addCacheContexts(['route', 'user']);
    }
    return AccessResult::forbidden();
  }

}
