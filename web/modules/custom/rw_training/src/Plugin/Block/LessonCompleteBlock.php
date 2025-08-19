<?php

namespace Drupal\rw_training\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\rw_training\Service\ProgressCalculator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Shows a "Complete / Uncomplete lesson" button on lesson pages.
 *
 * @Block(
 *   id = "rw_training_lesson_complete",
 *   admin_label = @Translation("Lesson complete toggle"),
 *   category = @Translation("RW Training")
 * )
 */
class LessonCompleteBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly ProgressCalculator $calculator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('rw_training.progress_calculator'),
    );
  }

  public function build(): array {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');

    if (is_numeric($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load((int) $node);
    }
    if (!$node instanceof NodeInterface || $node->bundle() !== 'lesson') {
      return [];
    }

    $account = \Drupal::currentUser();

    // Anonymous users: login prompt.
    if ($account->isAnonymous()) {
      $login = Link::createFromRoute(
        $this->t('Log in to track progress'),
        'user.login',
        [],
        [
          'query' => ['destination' => $node->toUrl()->toString()],
          'attributes' => ['class' => ['button']],
        ]
      )->toRenderable();

      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['rw-lesson-complete']],
        'login' => $login,
        '#cache' => [
          'contexts' => ['route', 'user'],
          'tags' => $node->getCacheTags(),
        ],
      ];
    }

    // Toggle link based on completion state.
    $completed = $this->calculator->isLessonCompleted($account, $node);

    $link = $completed
      ? Link::createFromRoute($this->t('Mark as not complete'), 'rw_training.lesson_uncomplete', ['node' => $node->id()], [
          'attributes' => ['class' => ['button', 'button--secondary']],
        ])->toRenderable()
      : Link::createFromRoute($this->t('Mark as complete'), 'rw_training.lesson_complete', ['node' => $node->id()], [
          'attributes' => ['class' => ['button', 'button--primary']],
        ])->toRenderable();

    $status_text = $completed ? $this->t('This lesson is marked complete.') : $this->t('This lesson is not complete.');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['rw-lesson-complete']],
      'status' => [
        '#markup' => '<p class="rw-lesson-complete__status">' . $status_text . '</p>',
      ],
      'action' => $link,
      '#cache' => [
        'contexts' => ['route', 'user'],
        'tags' => $node->getCacheTags(),
      ],
    ];
  }

  protected function blockAccess(AccountInterface $account): AccessResult {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');
    if (is_numeric($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load((int) $node);
    }
    if ($node instanceof NodeInterface && $node->bundle() === 'lesson') {
      return AccessResult::allowed()->addCacheContexts(['route', 'user']);
    }
    return AccessResult::forbidden();
  }

}
