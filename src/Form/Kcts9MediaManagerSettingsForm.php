<?php

namespace Drupal\kcts9_media_manager\Form;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\kcts9_media_manager\ApiClient;
use Drupal\kcts9_media_manager\ShowManager;
use Drupal\kcts9_media_manager\VideoContentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Kcts9MediaManagerSettingsForm.
 *
 * @ingroup kcts9_media_manager
 */
class Kcts9MediaManagerSettingsForm extends ConfigFormBase {

  /**
   * KCTS 9 Media Manager API client.
   *
   * @var \Drupal\kcts9_media_manager\ApiClient
   */
  protected $apiClient;

  /**
   * Date formatting service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a new Kcts9MediaManagerSettingsForm.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   Config factory service.
   * @param \Drupal\kcts9_media_manager\ApiClient $api_client
   *   Media Manager API client service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   */
  public function __construct(
    ConfigFactory $config_factory,
    ApiClient $api_client,
    DateFormatterInterface $date_formatter
  ) {
    parent::__construct($config_factory);
    $this->apiClient = $api_client;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('kcts9_media_manager.api_client'),
      $container->get('date.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_media_manager_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['kcts9_media_manager.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('kcts9_media_manager.settings');
    $interval_options = [3600, 10800, 21600, 43200, 86400, 604800];

    /*
     * Base API settings.
     */

    $form['api'] = [
      '#type' => 'details',
      '#title' => $this->t('Media Manager API settings'),
      '#open' => TRUE,
    ];

    $form['api']['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#description' => $this->t('PBS Media Manager API key.'),
      '#default_value' => $this->apiClient->getApiKey(),
    ];

    $form['api']['secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API secret'),
      '#description' => $this->t('PBS Media Manager API secret.'),
      '#default_value' => $this->apiClient->getApiSecret(),
    ];

    $form['api']['base_uri'] = [
      '#type' => 'select',
      '#title' => $this->t('Base endpoint'),
      '#description' => $this->t('PBS Media Manager API base endpoint.'),
      '#options' => [
        'staging' => $this->t('Staging'),
        'live' => $this->t('Production'),
      ],
    ];

    $base_uri = $this->apiClient->getApiEndPoint();
    if ($base_uri == ApiClient::LIVE) {
      $form['api']['base_uri']['#default_value'] = 'live';
    }
    else {
      $form['api']['base_uri']['#default_value'] = 'staging';
    }

    /*
     * Shows queue autoupdate settings.
     */

    $form['shows_queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Shows queue settings'),
      '#open' => TRUE,
    ];

    $form['shows_queue']['shows_queue_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automated queue building'),
      '#description' => $this->t('Enable incremental updates to local Show
        nodes from Media Manager data.'),
      '#default_value' => $config
        ->get(ShowManager::getAutoUpdateConfigName()),
      '#return_value' => TRUE,
    ];

    $form['shows_queue']['shows_queue_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Queue builder update interval'),
      '#description' => $this->t('How often to check Media Manager for
        new or updated shows to add to the queue. The queue itself is processed
        one every cron ron (or by an external cron operation).'),
      '#default_value' => $config
        ->get(ShowManager::getAutoUpdateIntervalConfigName()),
      '#options' => array_map(
        [$this->dateFormatter, 'formatInterval'],
        array_combine($interval_options, $interval_options)
      ),
      '#states' => [
        'visible' => [
          'input[name="shows_queue_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    /*
     * Video Content queue autoupdate settings.
     */

    $form['video_content_queue'] = [
      '#type' => 'details',
      '#title' => $this->t('Video Content queue settings'),
      '#description' => $this->t('The Video Content autoupdate process takes
        precedence over the Shows autoupdate process if both are configured to
        update automatically. When a Video Content queue autoupdate occurs 
        during cron execution, a Shows queue autoupdate will be skipped even if
        it will scheduled to run. This is to prevent potential time out issues
        for these two long-running processes.'),
      '#open' => TRUE,
    ];

    $form['video_content_queue']['video_content_queue_enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable automated queue building'),
      '#description' => $this->t('Enable incremental updates to local Video
        Content nodes from Media Manager data.'),
      '#default_value' => $config
        ->get(VideoContentManager::getAutoUpdateConfigName()),
      '#return_value' => TRUE,
    ];

    $form['video_content_queue']['video_content_queue_interval'] = [
      '#type' => 'select',
      '#title' => $this->t('Queue builder update interval'),
      '#description' => $this->t('How often to check Media Manager for
        new or updated assets to add to the queue. The queue itself is processed
        one every cron ron (or by an external cron operation).'),
      '#default_value' => $config
        ->get(VideoContentManager::getAutoUpdateIntervalConfigName()),
      '#options' => array_map(
        [$this->dateFormatter, 'formatInterval'],
        array_combine($interval_options, $interval_options)
      ),
      '#states' => [
        'visible' => [
          'input[name="video_content_queue_enable"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('kcts9_media_manager.settings');

    $config->set(ApiClient::CONFIG_KEY, $form_state->getValue('key'));
    $config->set(ApiClient::CONFIG_SECRET, $form_state->getValue('secret'));
    $config->set(
      ApiClient::CONFIG_BASE_URI,
      $form_state->getValue('base_uri')
    );

    $config->set(
      ShowManager::getAutoUpdateConfigName(),
      $form_state->getValue('shows_queue_enable')
    );

    if ($form_state->getValue('shows_queue_enable')) {
      $config->set(
        ShowManager::getAutoUpdateIntervalConfigName(),
        (int) $form_state->getValue('shows_queue_interval')
      );
    }

    $config->set(
      VideoContentManager::getAutoUpdateConfigName(),
      $form_state->getValue('video_content_queue_enable')
    );

    if ($form_state->getValue('video_content_queue_enable')) {
      $config->set(
        VideoContentManager::getAutoUpdateIntervalConfigName(),
        (int) $form_state->getValue('video_content_queue_interval')
      );
    }

    $config->save();

    parent::submitForm($form, $form_state);
  }

}
