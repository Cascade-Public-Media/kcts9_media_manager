<?php

namespace Drupal\kcts9_media_manager\Form;

use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\kcts9_media_manager\ApiContentManagerInterface;
use Drupal\kcts9_media_manager\ShowManager;
use Drupal\kcts9_media_manager\VideoContentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class QueueFormBase.
 *
 * @package Drupal\kcts9_media_manager\Form
 */
abstract class QueueFormBase extends FormBase {
  /**
   * Date formatting service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionList;

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * Queue worker manager service.
   *
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueWorkerManager;

  /**
   * Show manager.
   *
   * @var \Drupal\kcts9_media_manager\ShowManager
   */
  protected $showManager;

  /**
   * Video Content manager.
   *
   * @var \Drupal\kcts9_media_manager\VideoContentManager
   */
  protected $videoContentManager;

  /**
   * Constructs a ShowsQueueForm object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list
   *   Module extension list service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service.
   * @param \Drupal\Core\Queue\QueueWorkerManagerInterface $queue_worker_manager
   *   Queue worker manager service.
   * @param \Drupal\kcts9_media_manager\ShowManager $show_manager
   *   Show manager service.
   * @param \Drupal\kcts9_media_manager\VideoContentManager $video_content_manager
   *   Video Content manager service.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    EntityTypeManagerInterface $entity_type_manager,
    ModuleExtensionList $extension_list,
    QueueFactory $queue_factory,
    QueueWorkerManagerInterface $queue_worker_manager,
    ShowManager $show_manager,
    VideoContentManager $video_content_manager
  ) {
    $this->dateFormatter = $date_formatter;
    $this->entityTypeManager = $entity_type_manager;
    $this->extensionList = $extension_list;
    $this->queueFactory = $queue_factory;
    $this->queueWorkerManager = $queue_worker_manager;
    $this->showManager = $show_manager;
    $this->videoContentManager = $video_content_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('entity_type.manager'),
      $container->get('extension.list.module'),
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('kcts9_media_manager.show_manager'),
      $container->get('kcts9_media_manager.video_content_manager')
    );
  }

  /**
   * Creates a "queue status" form element.
   *
   * @param \Drupal\kcts9_media_manager\ApiContentManagerInterface $apiContentManager
   *   A queue manager.
   *
   * @return array
   *   Form element with queue status information.
   */
  protected function buildQueueStatusElement(ApiContentManagerInterface $apiContentManager): array {
    $config = $this->config('kcts9_media_manager.settings');
    $queue = $apiContentManager->getQueue();

    $last_update = $apiContentManager->getLastUpdateTime()->getTimestamp();
    $last_update_info = $this->t('@time (<em>@since ago</em>)', [
      '@time' => $this->dateFormatter->format($last_update, 'long'),
      '@since' => $this->dateFormatter->formatTimeDiffSince($last_update),
    ]);

    $element = ['#type' => 'container'];

    $element['header'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Queue Status'),
    ];

    $element['status'] = [
      '#type' => 'table',
      '#rows' => [
        [
          $this->t('Items in queue'),
          $this->t('@n <div class="description">Queue items are processed
            by cron or can be run manually (below).</div>', [
              '@n' => $queue->numberOfItems(),
            ]
          ),
        ],
        [
          $this->t('Last queue update'),
          $last_update_info,
        ],
        [
          $this->t('Automated update enabled?'),
          ($config->get($apiContentManager->getAutoUpdateConfigName()) ?
            $this->t('Yes') : $this->t('No')),
        ],
        [
          $this->t('Automated update interval'),
          $this->dateFormatter->formatInterval(
            $config->get($apiContentManager->getAutoUpdateIntervalConfigName())
          ),
        ],
      ],
    ];

    $element['actions']['#type'] = 'actions';
    $element['actions']['update_queue'] = [
      '#type' => 'submit',
      '#name' => 'update_queue',
      '#value' => $this->t('Update queue'),
      '#button_type' => 'primary',
    ];
    $element['actions']['run_queue'] = [
      '#type' => 'submit',
      '#name' => 'run_queue',
      '#value' => $this->t('Run queue'),
    ];
    if ($queue->numberOfItems() < 1) {
      $element['actions']['run_queue']['#disabled'] = TRUE;
    }
    $element['actions']['reset_queue'] = [
      '#type' => 'submit',
      '#name' => 'reset_queue',
      '#value' => $this->t('Reset queue'),
      '#button_type' => 'danger',
    ];

    return $element;
  }

}
