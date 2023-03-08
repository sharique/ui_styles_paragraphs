<?php

namespace Drupal\ui_styles_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\ui_styles\StylePluginManager;
use Drupal\ui_styles\StylePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a way to apply UI Styles to paragraphs.
 *
 * @ParagraphsBehavior(
 *   id = "ui_style_options",
 *   label = @Translation("UI Style Options"),
 *   description = @Translation("Integrates paragraphs with UI Styles module."),
 *   weight = 0
 * )
 */
class UIStyleOptions extends ParagraphsBehaviorBase {

  /** @var \Drupal\ui_styles\StylePluginManagerInterface */
  protected StylePluginManagerInterface $ui_styles_manager;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected TransliterationInterface $transliteration;

  /**
   * The key to store multiple groups in form state.
   */
  public const MULTIPLE_GROUPS_KEY = 'ui_styles_groups';

  /**
   * @param array $configuration
   * @param $plugin_id
   * @param $plugin_definition
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   * @param \Drupal\ui_styles\StylePluginManager $ui_styles_manager
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   */
  public function __construct(array                       $configuration, $plugin_id, $plugin_definition,
                              EntityFieldManagerInterface $entity_field_manager,
                              StylePluginManager          $ui_styles_manager,
                              TransliterationInterface    $transliteration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->ui_styles_manager = $ui_styles_manager;
    $this->transliteration = $transliteration;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.ui_styles'),
      $container->get('transliteration'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $enabled_style_ids = $this->getEnabledStyles();
    $def = [];
    foreach ($enabled_style_ids as $id) {
      $def[] = $this->ui_styles_manager->getDefinition($id);
    }
    // Load selected values.
    $selected = $this->getFlattenedSettings($paragraph);
    foreach ($def as $definition) {
      $id = $definition->id();
      $element_name = 'ui_styles_' . $id;
      $plugin_element = [
        '#type' => 'select',
        '#title' => $definition->getLabel(),
        '#options' => $definition->getOptionsAsOptions(),
        '#empty_option' => $this->t('- None -'),
        '#default_value' => $selected[$element_name] ?? '',
        '#weight' => $definition->getWeight(),
      ];

      // Create group if it does not exist yet.
      if ($definition->hasCategory()) {
        $group_key = $this->getMachineName($definition->getCategory());
        if (!isset($form[$group_key])) {
          $form[$group_key] = [
            '#type' => 'details',
            '#title' => $definition->getCategory(),
            '#open' => FALSE,
          ];
        }

        $form[$group_key][$element_name] = $plugin_element;
      }
      else {
        $form[$element_name] = $plugin_element;
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $classes = $this->getFlattenedSettings($paragraph);
    foreach ($classes as $key => $class) {
      if ($class) {
        $build['#attributes']['class'][] = $class;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $grouped_plugin_definitions = $this->ui_styles_manager->getGroupedDefinitions();
    if (empty($grouped_plugin_definitions)) {
      return $form;
    }
    $form_state->set(self::MULTIPLE_GROUPS_KEY, TRUE);
    if (\count($grouped_plugin_definitions) == 1) {
      $form_state->set(self::MULTIPLE_GROUPS_KEY, FALSE);
    }

    $form['enabled_styles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enabled Styles'),
      '#description' => $this->t('These are the styles types that will appear in the UI Styles dropdown.'),
      '#tree' => TRUE,
    ];

    // Until https://www.drupal.org/project/drupal/issues/2269823 is done, we
    // have to create the groups using separated form elements.
    foreach ($grouped_plugin_definitions as $groupedDefinitions) {
      $opened_group = FALSE;
      foreach ($groupedDefinitions as $definition) {
        $style_plugin_id = $definition->id();
        $default_value = \in_array($style_plugin_id, $this->configuration['enabled_styles'], TRUE) ? $style_plugin_id : NULL;

        print_r($default_value);
        // If the group has at least one style enabled. Display it opened.
        if (!$opened_group && $default_value !== NULL) {
          $opened_group = TRUE;
        }

        $plugin_element = [
          '#type' => 'checkbox',
          '#title' => !empty($definition->getLabel()) ? $definition->getLabel() : $style_plugin_id,
          '#return_value' => $style_plugin_id,
        ];

        // Create group if it does not exist yet.
        if ($form_state->get(self::MULTIPLE_GROUPS_KEY) && $definition->hasCategory()) {
          $group_key = $this->getMachineName($definition->getCategory());
          if (!isset($form['enabled_styles'][$group_key])) {
            $form['enabled_styles'][$group_key] = [
              '#type' => 'details',
              '#title' => $definition->getCategory(),
            ];
          }
          // @phpstan-ignore-next-line
          $form['enabled_styles'][$group_key]['#open'] = $opened_group;
          if (isset($this->configuration['enabled_styles'][$group_key])) {
            $plugin_element['#default_value'] = $this->configuration['enabled_styles'][$group_key][$style_plugin_id] ? $style_plugin_id : '';
          }
          $form['enabled_styles'][$group_key][$style_plugin_id] = $plugin_element;
        }
        else {
          $plugin_element['#default_value'] = $default_value;
          $form['enabled_styles'][$style_plugin_id] = $plugin_element;
        }
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['enabled_styles'] = $form_state->getValue('enabled_styles');
  }

  /**
   * Generates a machine name from a string.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|string $string
   *   The string to convert.
   *
   * @return string
   *   The converted string.
   *
   * @see \Drupal\Core\Block\BlockBase::getMachineNameSuggestion()
   * @see \Drupal\system\MachineNameController::transliterate()
   */
  protected function getMachineName($string): string {
    $transliterated = $this->transliteration->transliterate($string, LanguageInterface::LANGCODE_DEFAULT, '_');
    $transliterated = \mb_strtolower($transliterated);
    $transliterated = \preg_replace('@[^a-z0-9_.]+@', '_', $transliterated);
    return $transliterated ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return ['enabled_styles' => []];
  }

  /**
   * Return enabled styles as flattened array.
   *
   * @return array
   *   List of enabled styles.
   */
  public function getEnabledStyles(): array {
    // Get enabled styles.
    $enabled_styles = NestedArray::filter($this->configuration['enabled_styles']);
    foreach ($enabled_styles as $group_key => $group_styles) {
      // Style without group will directly be 0 or the style id.
      if (!\is_array($group_styles)) {
        $flattened_style_ids[$group_key] = $group_styles;
      }
      else {
        foreach ($group_styles as $style_id => $style_value) {
          $flattened_style_ids[$style_id] = $style_value;
        }
      }
    }

    $enabled_style_ids = \array_values(\array_filter($flattened_style_ids));
    return $enabled_style_ids;
  }

  /**
   * Get settings for paragraph as flattened array.
   *
   * @param \Drupal\paragraphs\Entity\Paragraph $paragraph
   *  The paragraph entity.
   * @return array
   *  Flattened array.
   */
  public function getFlattenedSettings(Paragraph $paragraph): array {
    $values = $paragraph->getBehaviorSetting($this->pluginId, []);
    $classes = [];
    foreach ($values as $group_key => $group_styles) {
      // Style without group will directly be 0 or the style id.
      if (!\is_array($group_styles)) {
        $classes[$group_key] = $group_styles;
      }
      else {
        foreach ($group_styles as $style_id => $style_value) {
          $classes[$style_id] = $style_value;
        }
      }
    }
    return $classes;
  }

}
