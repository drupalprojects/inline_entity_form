<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Element\InlineEntityForm.
 */

namespace Drupal\inline_entity_form\Element;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Element\RenderElement;
use Drupal\inline_entity_form\ElementSubmit;

/**
 * Provides an inline entity form element.
 *
 * @RenderElement("inline_entity_form")
 */
class InlineEntityForm extends RenderElement {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#ief_id' => '',
      // Instance of \Drupal\Core\Entity\EntityInterface. Entity that will be
      // displayed in entity form. Can be unset if #enatity_type and #bundle
      // are provided and #op equals 'add'.
      '#entity' => NULL,
      '#entity_type' => NULL,
      '#bundle' => NULL,
      '#language' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      '#op' => 'add',
      // Will save entity on submit if set to TRUE.
      '#save_entity' => TRUE,
      // Needs to be set to FALSE if one wants to implement it's own submit logic.
      '#handle_submit' => TRUE,
      '#process' => [
        [$class, 'processEntityForm'],
      ],
      '#element_validate' => [
        [$class, 'validateEntityForm'],
      ],
      '#ief_element_submit' => [
        [$class, 'submitEntityForm'],
      ],
      '#theme_wrappers' => ['container'],
      // Allow inline forms to use the #fieldset key.
      '#pre_render' => [
        [$class, 'addFieldsetMarkup'],
      ],
    ];
  }

  /**
   * Builds the entity form using the inline form handler.
   *
   * @param array $entity_form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   * @param array $complete_form
   *   The complete form structure.
   *
   * @return array
   *   The built entity form.
   */
  public static function processEntityForm($entity_form, FormStateInterface $form_state, &$complete_form) {
    if (empty($entity_form['#ief_id'])) {
      $entity_form['#ief_id'] = \Drupal::service('uuid')->generate();
    }
    if (empty($entity_form['#entity_type']) && !empty($entity_form['#entity']) && $entity_form['#entity'] instanceof EntityInterface) {
      $entity_form['#entity_type'] = $entity_form['#entity']->getEntityTypeId();
    }
    if (empty($entity_form['#bundle']) && !empty($entity_form['#entity']) && $entity_form['#entity'] instanceof EntityInterface) {
      $entity_form['#bundle'] = $entity_form['#entity']->bundle();
    }

    // We can't do anything useful if we don't know which entity type/ bundle
    // we're supposed to operate with.
    if (empty($entity_form['#entity_type']) || empty($entity_form['#bundle'])) {
      return $entity_form;
    }

    // If entity object is not there we're displaying the add form. We need to
    // create a new entity to be used with it.
    if (empty($entity_form['#entity'])) {
      if ($entity_form['#op'] == 'add') {
        $entity_type = \Drupal::entityTypeManager()->getDefinition($entity_form['#entity_type']);
        $storage = \Drupal::entityTypeManager()->getStorage($entity_form['#entity_type']);
        $values = [
          'langcode' => $entity_form['#language'],
        ];
        if ($bundle_key = $entity_type->getKey('bundle')) {
          $values[$bundle_key] = $entity_form['#bundle'];
        }
        $entity_form['#entity'] = $storage->create($values);
      }
    }

    // Put some basic information about IEF into form state.
    $state = $form_state->has(['inline_entity_form', $entity_form['#ief_id']]) ? $form_state->get(['inline_entity_form', $entity_form['#ief_id']]) : [];
    $state += [
      'op' => $entity_form['#op'],
      'entity' => $entity_form['#entity'],
    ];
    $form_state->set(['inline_entity_form', $entity_form['#ief_id']], $state);

    $inline_form_handler = static::getInlineFormHandler($entity_form['#entity_type']);
    $entity_form = $inline_form_handler->entityForm($entity_form, $form_state);

    // Attach submit callbacks to main submit buttons.
    if ($entity_form['#handle_submit']) {
      ElementSubmit::attach($complete_form, $form_state);
    }

    return $entity_form;
  }

  /**
   * Validates the entity form using the inline form handler.
   *
   * @param array $entity_form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateEntityForm($entity_form, FormStateInterface $form_state) {
    $inline_form_handler = static::getInlineFormHandler($entity_form['#entity_type']);
    $inline_form_handler->entityFormValidate($entity_form, $form_state);
  }

  /**
   * Handles the submission of the entity form using the inline form handler.
   *
   * @param array $entity_form
   *   The entity form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function submitEntityForm(&$entity_form, FormStateInterface $form_state) {
    $inline_form_handler = static::getInlineFormHandler($entity_form['#entity_type']);
    $inline_form_handler->entityFormSubmit($entity_form, $form_state);
  }

  /**
   * Gets the inline form handler for the given entity type.
   *
   * @param string $entity_type
   *   The entity type id.
   *
   * @throws \InvalidArgumentException
   *   Thrown when the entity type has no inline form handler defined.
   *
   * @return \Drupal\inline_entity_form\InlineFormInterface
   *   The inline form handler.
   */
  public static function getInlineFormHandler($entity_type) {
    $inline_form_handler = \Drupal::entityTypeManager()->getHandler($entity_type, 'inline_form');
    if (empty($inline_form_handler)) {
      throw new \InvalidArgumentException(sprintf('The %s entity type has no inline form handler.', $entity_type));
    }

    return $inline_form_handler;
  }

  /**
   * Pre-render callback: Move form elements into fieldsets.
   *
   * Inline forms use #tree = TRUE to keep their values in a hierarchy for
   * easier storage. Moving the form elements into fieldsets during form
   * building would break up that hierarchy, so it's not an option for entity
   * fields. Therefore, we wait until the pre_render stage, where any changes
   * we make affect presentation only and aren't reflected in $form_state.
   */
  public static function addFieldsetMarkup($form) {
    $sort = [];
    foreach (Element::children($form) as $key) {
      $element = $form[$key];
      // In our form builder functions, we added an arbitrary #fieldset property
      // to any element that belongs in a fieldset. If this form element has that
      // property, move it into its fieldset.
      if (isset($element['#fieldset']) && isset($form[$element['#fieldset']])) {
        $form[$element['#fieldset']][$key] = $element;
        // Remove the original element this duplicates.
        unset($form[$key]);
        // Mark the fieldset for sorting.
        if (!in_array($key, $sort)) {
          $sort[] = $element['#fieldset'];
        }
      }
    }

    // Sort all fieldsets, so that element #weight stays respected.
    foreach ($sort as $key) {
      uasort($form[$key], '\Drupal\Component\Utility\SortArray::sortByWeightProperty');
    }

    return $form;
  }

}
