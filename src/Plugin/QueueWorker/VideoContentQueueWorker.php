<?php

namespace Drupal\kcts9_media_manager\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\kcts9_media_manager\VideoContentManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes Asset data from the Media Manager API to Video Content nodes.
 *
 * Queue items are added by cron processing.
 *
 * @QueueWorker(
 *   id = "kcts9_media_manager.queue.video_content",
 *   title = @Translation("Media Manager Assets processor"),
 *   cron = {"time" = 60}
 * )
 *
 * @see kcts9_media_manager_cron()
 * @see \Drupal\Core\Annotation\QueueWorker
 * @see \Drupal\Core\Annotation\Translation
 */
class VideoContentQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * KCTS 9 Media Manager logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  private $logger;

  /**
   * Video Content manager.
   *
   * @var \Drupal\kcts9_media_manager\VideoContentManager
   */
  private $videoContentManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration,
    $plugin_id,
    $plugin_definition,
    LoggerChannelInterface $logger,
    VideoContentManager $show_manager
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->videoContentManager = $show_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.kcts9_media_manager'),
      $container->get('kcts9_media_manager.video_content_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($item): void {
    $this->videoContentManager->addOrUpdateVideoContent(
      $item['asset'],
      $item['show']
    );
  }

}
