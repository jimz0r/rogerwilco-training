<?php

namespace Drupal\rw_training;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Link;

/**
 * Provides a listing of Enrollment entities.
 */
class EnrollmentListBuilder extends EntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader(): array {
    $header['id'] = $this->t('ID');
    $header['user'] = $this->t('User');
    $header['course'] = $this->t('Course');
    $header['status'] = $this->t('Status');
    $header['percent'] = $this->t('% Complete');
    $header['score'] = $this->t('Score');
    $header['enrolled'] = $this->t('Enrolled on');
    $header['completed'] = $this->t('Completed on');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity): array {
    /** @var \Drupal\rw_training\Entity\Enrollment $entity */
    $user = $entity->get('user_id')->entity;
    $course = $entity->get('course')->entity;

    $row['id'] = $entity->id();
    $row['user'] = $user
      ? Link::fromTextAndUrl($user->label(), $user->toUrl())
      : $this->t('-');
    $row['course'] = $course
      ? Link::fromTextAndUrl($course->label(), $course->toUrl())
      : $this->t('-');
    $row['status'] = $entity->get('status')->value ?? '-';

    $percent = (float) ($entity->get('percent_complete')->value ?? 0);
    $row['percent'] = number_format($percent, 2) . '%';

    $score = (float) ($entity->get('score')->value ?? 0);
    $row['score'] = number_format($score, 2);

    $df = \Drupal::service('date.formatter');
    $created = (int) ($entity->get('created')->value ?? 0);
    $completed = (int) ($entity->get('completed')->value ?? 0);
    $row['enrolled'] = $created ? $df->format($created, 'short') : '-';
    $row['completed'] = $completed ? $df->format($completed, 'short') : '-';

    return $row + parent::buildRow($entity);
  }

}
