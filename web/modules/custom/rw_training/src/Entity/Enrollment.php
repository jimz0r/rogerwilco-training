<?php

namespace Drupal\rw_training\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\user\UserInterface;

/**
 * Defines the Enrollment entity.
 *
 * @ContentEntityType(
 *   id = "rw_enrollment",
 *   label = @Translation("Enrollment"),
 *   handlers = {
 *     "list_builder" = "Drupal\rw_training\EnrollmentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\rw_training\EnrollmentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\Core\Entity\ContentEntityForm",
 *       "edit" = "Drupal\Core\Entity\ContentEntityForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider"
 *     }
 *   },
 *   base_table = "rw_enrollment",
 *   admin_permission = "administer rw training",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "uid" = "user_id"
 *   },
 *   links = {
 *     "canonical"   = "/admin/content/enrollments/{rw_enrollment}",
 *     "add-form"    = "/admin/content/enrollments/add",
 *     "edit-form"   = "/admin/content/enrollments/{rw_enrollment}/edit",
 *     "delete-form" = "/admin/content/enrollments/{rw_enrollment}/delete",
 *     "collection"  = "/admin/content/enrollments"
 *   }
 * )
 */
class Enrollment extends ContentEntityBase {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['user_id'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(static::class . '::getCurrentUserId')
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'author',
        'weight' => -10,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -10,
      ]);

    $fields['course'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Course'))
      ->setRequired(TRUE)
      ->setSetting('target_type', 'node')
      ->setSetting('handler_settings', [
        'target_bundles' => ['course' => 'course'],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'entity_reference_label',
        'weight' => -9,
      ])
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'weight' => -9,
      ]);

    $fields['status'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Status'))
      ->setRequired(TRUE)
      ->setSettings([
        'allowed_values' => [
          'enrolled' => 'Enrolled',
          'in_progress' => 'In progress',
          'completed' => 'Completed',
        ],
      ])
      ->setDefaultValue('enrolled')
      ->setDisplayOptions('view', ['type' => 'list_default', 'weight' => -8])
      ->setDisplayOptions('form', ['type' => 'options_select', 'weight' => -8]);

    $fields['percent_complete'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Percent complete'))
      ->setDescription(t('Course completion percentage for this user.'))
      ->setSetting('precision', 5)
      ->setSetting('scale', 2)
      ->setDefaultValue('0.00')
      ->setDisplayOptions('view', ['type' => 'number_decimal', 'weight' => -7])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => -7]);

    $fields['score'] = BaseFieldDefinition::create('decimal')
      ->setLabel(t('Score'))
      ->setDescription(t('Latest overall score for this enrollment.'))
      ->setSetting('precision', 5)
      ->setSetting('scale', 2)
      ->setDefaultValue('0.00')
      ->setDisplayOptions('view', ['type' => 'number_decimal', 'weight' => -6])
      ->setDisplayOptions('form', ['type' => 'number', 'weight' => -6]);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Enrolled on'));

    $fields['completed'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Completed on'))
      ->setDisplayOptions('view', ['type' => 'timestamp', 'weight' => -5])
      ->setDisplayOptions('form', ['type' => 'datetime_timestamp', 'weight' => -5]);

    return $fields;
  }

  /**
   * Default value callback for user_id.
   */
  public static function getCurrentUserId(): array {
    $account = \Drupal::currentUser();
    return $account ? [['target_id' => $account->id()]] : [];
  }

  /** Convenience getter: enrolled User. */
  public function getUser(): ?UserInterface {
    return $this->get('user_id')->entity;
  }

  /** Convenience getter: Course node. */
  public function getCourse(): ?NodeInterface {
    return $this->get('course')->entity;
  }

  /**
   * {@inheritdoc}
   * Provide a non-null label to avoid UI fatals on edit pages.
   */
  public function label(): string {
    $user = $this->get('user_id')->entity;
    $course = $this->get('course')->entity;

    $user_label = $user ? $user->label() : (string) $this->t('Unknown user');
    $course_label = $course ? $course->label() : (string) $this->t('Unknown course');

    // Example: "Enrollment: Jane Doe → Site Building 101"
    return (string) $this->t('Enrollment: @user → @course', [
      '@user' => $user_label,
      '@course' => $course_label,
    ]);
  }

}
