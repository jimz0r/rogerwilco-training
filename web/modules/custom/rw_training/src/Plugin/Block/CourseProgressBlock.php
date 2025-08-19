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
 * Shows % complete + Continue button on Course nodes.
 *
 * @Block(
 *   id = "rw_training_course_progress",
 *   admin_label = @Translation("Course progress"),
 *   category = @Translation("RW Training")
 * )
 */
class CourseProgressBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    if (!$node instanceof NodeInterface || $node->bundle() !== 'course') {
      return [];
    }

    $account = \Drupal::currentUser();

    // Anonymous users see a login CTA.
    if ($account->isAnonymous()) {
      $login = Link::createFromRoute(
        $this->t('Log in to view your progress'),
        'user.login',
        [],
        ['query' => ['destination' => $node->toUrl()->toString()], 'attributes' => ['class' => ['button']]]
      )->toRenderable();

      return [
        '#type' => 'container',
        '#attributes' => ['class' => ['rw-course-progress']],
        'login' => $login,
        '#cache' => ['contexts' => ['route', 'user'], 'tags' => $node->getCacheTags()],
      ];
    }

    // Compute progress.
    $percent = $this->calculator->getProgressPercent($account, $node);
    [$done, $total] = $this->calculator->getProgressCounts($account, $node);

    // "Continue" link or "Review course" if done.
    $next = $this->calculator->getNextIncompleteLesson($account, $node);
    $primary = $next
      ? Link::createFromRoute($this->t('Continue'), 'rw_training.course_next_lesson', ['node' => $node->id()], [
          'attributes' => ['class' => ['button', 'button--primary']],
        ])->toRenderable()
      : Link::createFromRoute($this->t('Review course'), 'entity.node.canonical', ['node' => $node->id()], [
          'attributes' => ['class' => ['button', 'button--secondary']],
        ])->toRenderable();

    // Accessible native progress element.
    $progress = [
      '#type' => 'html_tag',
      '#tag' => 'progress',
      '#attributes' => [
        'value' => (string) $percent,
        'max' => '100',
        'aria-label' => $this->t('Course progress'),
        'class' => ['rw-course-progress__bar'],
      ],
    ];

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['rw-course-progress']],
      'summary' => [
        '#markup' => '<p class="rw-course-progress__summary">' .
          $this->t('@percent% complete (@done of @total lessons)', [
            '@percent' => number_format($percent, 2),
            '@done' => $done,
            '@total' => $total,
          ]) .
          '</p>',
      ],
      'bar' => $progress,
      'action' => $primary,
      '#cache' => ['contexts' => ['route', 'user'], 'tags' => $node->getCacheTags()],
    ];
  }

  protected function blockAccess(AccountInterface $account): AccessResult {
    $route_match = \Drupal::routeMatch();
    $node = $route_match->getParameter('node');
    if (is_numeric($node)) {
      $node = \Drupal::entityTypeManager()->getStorage('node')->load((int) $node);
    }
    if ($node instanceof NodeInterface && $node->bundle() === 'course') {
      return AccessResult::allowed()->addCacheContexts(['route', 'user']);
    }
    return AccessResult::forbidden();
  }

}
