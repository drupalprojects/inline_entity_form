<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormSingle.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Single value widget.
 *
 * @FieldWidget(
 *   id = "inline_entity_form_single",
 *   label = @Translation("Inline entity form - Single value"),
 *   field_types = {
 *     "entity_reference"
 *   },
 *   multiple_values = false
 * )
 */
class InlineEntityFormSingle extends InlineEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $ief_id = $this->fieldDefinition;

    return $element;
  }

}
