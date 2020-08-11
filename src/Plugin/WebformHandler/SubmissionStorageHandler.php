<?php

namespace Drupal\webform_submission_storage\Plugin\WebformHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionConditionsValidatorInterface;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\WebformTokenManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\webform_submission_storage\StorageServiceInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Component\Serialization\Json;

/**
 * Webform example handler.
 *
 * @WebformHandler(
 *   id = "webform_submission_storage",
 *   label = @Translation("Submission storage"),
 *   category = @Translation("Form Handler"),
 *   description = @Translation("Save submission to custom entity or custom table"),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 * )
 */
class SubmissionStorageHandler extends WebformHandlerBase {

  /**
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;


  /**
   * Braze submission service.
   *
   * @var \Drupal\webform_custom_storage\StorageService
   */
  protected $storageService;

  /**
   * Drupal\Core\Logger\LoggerChannelFactoryInterface definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelFactoryInterface $logger_factory, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, WebformSubmissionConditionsValidatorInterface $conditions_validator, WebformTokenManagerInterface $token_manager, StorageServiceInterface $storage_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger_factory, $config_factory, $entity_type_manager, $conditions_validator);
    $this->tokenManager = $token_manager;
    $this->storageService = $storage_service;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('webform_submission.conditions_validator'),
      $container->get('webform.token_manager'),
      $container->get('webform_submission_storage.storage_service'),
      $container->get('logger.factory')
    );
  }

  /**
   * Default configuration.
   */
  public function defaultConfiguration() {
    return [
      'storage_type' => '',
      'storage_key' => '',
      'storage_fields_mapping' => '',
      'debug' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Additional.
    $form['additional'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Additional settings'),
    ];

    $form['additional']['storage_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Select storage type'),
      '#options' => [
        'entity' => 'Entity',
        'table' => 'Custom DB table',
      ],
      '#required' => TRUE,
      '#description' => $this->t('Select storage type.'),
      '#default_value' => $this->configuration['storage_type'],
    ];

    $form['additional']['storage_key'] = [
      '#type' => 'textfield',
      '#title' => 'Storage key',
      '#description' => $this->t('Enter entity machine name for entity or database table name.'),
      '#default_value' => $this->configuration['storage_key'],
    ];

    $form['additional']['storage_fields_mapping'] = [
      '#type' => 'webform_codemirror',
      '#mode' => 'yaml',
      '#title' => $this->t('Fields mapping'),
      '#description' => $this->t('Enter field mapping in yml format'),
      '#default_value' => $this->configuration['storage_fields_mapping'],
      '#suffix' => $this->t('<div role="contentinfo" class="messages messages--info">
        field_storage_key: field_webform_key
      </div>'),
    ];

    // Development.
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, posted submissions will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];

    $this->elementTokenValidate($form);

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->applyFormStateToConfiguration($form_state);
    // Cast debug.
    $this->configuration['debug'] = (bool) $this->configuration['debug'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary() {
    $configuration = $this->getConfiguration();
    $settings = $configuration['settings'];
    return [
      '#settings' => $settings,
    ] + parent::getSummary();

  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    if (!empty($this->configuration['storage_type'])) {
      $data_mapping = (!empty($this->configuration['storage_fields_mapping'])) ? $this->configuration['storage_fields_mapping'] : '';
      $data = [];

      if (!empty($data_mapping)) {
        $data_replace = $this->replaceTokens($data_mapping, $webform_submission);
        $data_replace_decode = Yaml::decode($data_replace);
        $data = $webform_submission->getData();
        if ($this->configuration['debug']) {
          $this->loggerFactory->get('webform_submission_storage')->info('Config data: @config_data, Data: @data', [
            '@config_data' => Json::encode($data_replace_decode),
            '@data' => Json::encode($data),
          ]);
        }
      }

      if (!empty($this->configuration['storage_type']) && !empty($this->configuration['storage_key']) && $this->configuration['storage_type'] == 'entity') {
        $this->storageService->storageSubmitEntity($this->configuration, $data_replace_decode);
      }

      if (!empty($this->configuration['storage_type']) && !empty($this->configuration['storage_key']) && $this->configuration['storage_type'] == 'table') {
        $this->storageService->storageSubmitTable($this->configuration, $data_replace_decode);
      }
    }
    else {
      $this->loggerFactory->get('webform_submission_storage')->error('Storage type is not configured. Check webform handler settings');
    }

  }

}
