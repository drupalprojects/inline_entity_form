<?php

/**
 * @file
 * Contains \Drupal\inline_entity_form\Plugin\Field\FieldWidget\InlineEntityFormBase.
 */

namespace Drupal\inline_entity_form\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Inline entity form widget base class (shared between single and multiple).
 */
abstract class InlineEntityFormBase extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The inline entity form id.
   *
   * @var string
   */
  protected $iefId;

  /**
   * The entity manager.
   *
   * @var \Drupal\Core\Entity\EntityManagerInterface
   */
  protected $entityManager;

  /**
   * The inline entity from handler.
   *
   * @var \Drupal\inline_entity_form\InlineEntityFormHandlerInterface
   */
  protected $iefHandler;

  /**
   * Constructs an InlineEntityFormBase object.
   *
   * @param array $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityManagerInterface $entity_manager
   *   Entity manager service.
   */
  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, array $third_party_settings, EntityManagerInterface $entity_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
    $this->entityManager = $entity_manager;

    $this->initializeIefController();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('entity.manager')
    );
  }

  /**
   * Initializes IEF form handler.
   */
  protected function initializeIefController() {
    if (!isset($this->iefHandler)) {
      $target_type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
      $this->iefHandler = $this->entityManager->getHandler($target_type, 'inline entity form');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    $keys = array_diff(parent::__sleep(), array('iefHandler'));
    return $keys;
  }

  /**
   * {@inheritdoc}
   */
  public function __wakeup() {
    parent::__wakeup();
    $this->initializeIefController();
  }

  /**
   * Sets inline entity form ID.
   *
   * @param string $ief_id
   *   The inline entity form ID.
   */
  protected function setIefId($ief_id) {
    $this->iefId = $ief_id;
  }

  /**
   * Gets inline entity form ID.
   *
   * @return string
   *   Inline entity form ID.
   */
  protected function getIefId() {
    return $this->iefId;
  }

  /**
   * Gets the target bundles for the current field.
   *
   * @return string[]
   *   A list of bundles.
   */
  protected function getTargetBundles() {
    $settings = $this->getFieldSettings();
    if (!empty($settings['handler_settings']['target_bundles'])) {
      $target_bundles = array_values($settings['handler_settings']['target_bundles']);
    }
    else {
      // If no target bundles have been specified then all are available.
      $target_bundles = array_keys($this->entityManager->getBundleInfo($settings['target_type']));
    }

    return $target_bundles;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'override_labels' => FALSE,
      'label_singular' => '',
      'label_plural' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $states_prefix = 'fields[' . $this->fieldDefinition->getName() . '][settings_edit_form][settings]';
    $element = [];
    $element['override_labels'] = [
      '#type' => 'checkbox',
      '#title' => t('Override labels'),
      '#default_value' => $this->settings['override_labels'],
    ];
    $element['label_singular'] = [
      '#type' => 'textfield',
      '#title' => t('Singular label'),
      '#default_value' => $this->settings['label_singular'],
      '#states' => [
        'visible' => [
          ':input[name="' . $states_prefix . '[override_labels]"]' => ['checked' => TRUE],
        ],
      ],
    ];
    $element['label_plural'] = array(
      '#type' => 'textfield',
      '#title' => t('Plural label'),
      '#default_value' => $this->settings['label_plural'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => ['checked' => TRUE],
        ),
      ),
    );

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    if ($this->settings['override_labels']) {
      $summary[] = t(
        'Overriden labels are used: %singular and %plural',
        ['%singular' => $this->settings['label_singular'], '%plural' => $this->settings['label_plural']]
      );
    }
    else {
      $summary[] = t('Default labels are used.');
    }

    return $summary;
  }

  /**
   * Returns an array of entity type labels to be included in the UI text.
   *
   * @return array
   *   Associative array with the following keys:
   *   - 'singular': The label for singular form.
   *   - 'plural': The label for plural form.
   */
  protected function labels() {
    // The admin has specified the exact labels that should be used.
    if ($this->settings['override_labels']) {
      return [
        'singular' => $this->settings['label_singular'],
        'plural' => $this->settings['label_plural'],
      ];
    }
    else {
      $this->initializeIefController();
      return $this->iefHandler->labels();
    }
  }

}
