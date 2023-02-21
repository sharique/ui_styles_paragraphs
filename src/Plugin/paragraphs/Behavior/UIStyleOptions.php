<?php
namespace  Drupal\ui_styles_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Drupal\ui_styles\StylePluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a way to define grid based layouts.
 *
 * @ParagraphsBehavior(
 *   id = "ui_style_options",
 *   label = @Translation("UI Style Optiona"),
 *   description = @Translation("Integrates paragraphs with UI Style Options."),
 *   weight = 0
 * )
 */
class UIStyleOptions extends ParagraphsBehaviorBase {

  /** @var \Drupal\ui_styles\StylePluginManager */
  protected $ui_styles_manager;

  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              EntityFieldManagerInterface $entity_field_manager,
                              StylePluginManager $ui_styles_manager)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_field_manager);
    $this->ui_styles_manager = $ui_styles_manager;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.ui_styles'),
    );
  }

  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state)
  {
    $values = [];
    $def = $this->ui_styles_manager->getSortedDefinitions();
    $keys = array_keys($def);
    foreach ($keys as $key) {
      $values[$key] = $paragraph->getBehaviorSetting($this->pluginId, 'ui_styles_' . $key);
    }

    $form = $this->ui_styles_manager->alterForm($form, $values);
    return $form;
  }

  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode)
  {
    $values = $this->getStyleValues($paragraph);

    foreach ($values as $key => $class) {
      if ($class) {
        $build['#attributes']['class'][] = $class;
      }
    }
  }

  /**
   * @param ParagraphInterface $paragraph
   * @return array
   */
  protected function getStyleValues(ParagraphInterface $paragraph): array
  {
    $values = [];
    $def = $this->ui_styles_manager->getSortedDefinitions();
    $keys = array_keys($def);
    foreach ($keys as $key) {
      $values[$key] = $paragraph->getBehaviorSetting($this->pluginId, 'ui_styles_' . $key);
    }
    return $values;
  }
}
