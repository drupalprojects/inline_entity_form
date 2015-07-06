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
   * Options for match operator.
   *
   * @var array
   */
  protected $matchOperatorOptions;

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
    $this->matchOperatorOptions = [
      'STARTS_WITH' => t('Starts with'),
      'CONTAINS' => t('Contains'),
    ];

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
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      "allow_existing" => FALSE,
      "match_operator" => "CONTAINS",
      "delete_references" => FALSE,
      "override_labels" => FALSE,
      "label_singular" => "",
      "label_plural" => "",
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $labels = $this->labels();
    $states_prefix = 'instance[widget][settings][type_settings]';

    $element['allow_existing'] = array(
      '#type' => 'checkbox',
      '#title' => t('Allow users to add existing @label.', array('@label' => $labels['plural'])),
      '#default_value' => $this->settings['allow_existing'],
    );
    $element['match_operator'] = array(
      '#type' => 'select',
      '#title' => t('Autocomplete matching'),
      '#default_value' => $this->settings['match_operator'],
      '#options' => $this->matchOperatorOptions,
      '#description' => t('Select the method used to collect autocomplete suggestions. Note that <em>Contains</em> can cause performance issues on sites with thousands of nodes.'),
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[allow_existing]"]' => array('checked' => TRUE),
        ),
      ),
    );
    // The single widget doesn't offer autocomplete functionality.
    if ($form_state->get(['widget', 'type']) == 'inline_entity_form_single') {
      $form['allow_existing']['#access'] = FALSE;
      $form['match_operator']['#access'] = FALSE;
    }

    $element['delete_references'] = array(
      '#type' => 'checkbox',
      '#title' => t('Delete referenced @label when the parent entity is deleted.', array('@label' => $labels['plural'])),
      '#default_value' => $this->settings['delete_references'],
    );

    $element['override_labels'] = array(
      '#type' => 'checkbox',
      '#title' => t('Override labels'),
      '#default_value' => $this->settings['override_labels'],
    );
    $element['label_singular'] = array(
      '#type' => 'textfield',
      '#title' => t('Singular label'),
      '#default_value' => $this->settings['label_singular'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => array('checked' => TRUE),
        ),
      ),
    );
    $element['label_plural'] = array(
      '#type' => 'textfield',
      '#title' => t('Plural label'),
      '#default_value' => $this->settings['label_plural'],
      '#states' => array(
        'visible' => array(
          ':input[name="' . $states_prefix . '[override_labels]"]' => array('checked' => TRUE),
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

    if ($this->settings['allow_existing']) {
      $summary[] = t(
        'Existing entities can be referenced and are matched with %operator operator.',
        ['%operator' => $this->matchOperatorOptions[$this->settings['match_operator']]]
      );
    }
    else {
      $summary[] = t('Existing entities can not be referenced.');
    }

    $summary[] = $this->settings['delete_references'] ? t('Referenced entities are deleted when reference is lost.') : t('Referenced entities are persisted when reference is lost.');
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
