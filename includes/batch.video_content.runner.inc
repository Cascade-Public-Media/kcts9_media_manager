<?php

/**
 * @file
 * Batch callbacks for the Video Content queue runner.
 */

use Drupal\kcts9_media_manager\ShowManager;
use Drupal\kcts9_media_manager\VideoContentManager;

/**
 * Batch callback to run the Video Content update queue.
 *
 * @param array $context
 *   Batch processing context information.
 *
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function kcts9_media_manager_batch_video_content_run(array &$context): void {
  /** @var \Drupal\kcts9_media_manager\ApiClient $apiClient */
  $apiClient = Drupal::service('kcts9_media_manager.api_client');
  /** @var \Drupal\Core\Queue\QueueInterface $queue */
  $queue = Drupal::service('queue')->get(VideoContentManager::BATCH_QUEUE_NAME);
  $itemsRemaining = $queue->numberOfItems();
  /** @var \Drupal\kcts9_media_manager\VideoContentManager $videoContentManager */
  $videoContentManager = Drupal::service('kcts9_media_manager.video_content_manager');

  if (empty($context['sandbox'])) {
    $context['sandbox']['progress'] = 0;
    $context['sandbox']['max'] = $itemsRemaining;
    $context['results']['assets_processed'] = 0;
    $context['results']['warnings'] = [];
  }

  $max = min(5, $itemsRemaining);
  for ($i = 0; $i < $max; $i++) {
    $item = $queue->claimItem(30);
    $show_id = $item->data['show-id'];
    $page = $item->data['page'];

    try {
      $assets = $apiClient->getAssets([
        'show-id' => $show_id,
        'page' => $page,
      ]);
    }
    catch (Exception $e) {
      // An exception may be thrown if a page doesn't actually exist. Execution
      // should not stop for it.
      watchdog_exception('kcts9_media_manager', $e);
      $assets = [];
    }

    // Verify that a Show node exists for the parent of this Asset.
    $show = $videoContentManager
      ->getNodeByGuid($show_id, ShowManager::getBundleId());
    if (empty($show)) {
      $context['results']['warnings'][$show_id] = t(
        'Could not find Show node for Media Manager GUID @guid.',
        ['@guid' => $show_id]
      );
      return;
    }

    foreach ($assets as $asset) {
      $videoContentManager->addOrUpdateVideoContent($asset, $show, TRUE);
      $context['results']['assets_processed']++;
    }

    $queue->deleteItem($item);

    $context['sandbox']['progress']++;
  }

  if (isset($asset)) {
    $context['message'] = t('Total assets processed so far: @total. Last asset
      processed: @title (@id)', [
        '@total' => number_format($context['results']['assets_processed']),
        '@title' => $asset->attributes->title,
        '@id' => $asset->id,
      ]);
  }

  if ($context['sandbox']['progress'] != $context['sandbox']['max']) {
    $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
  }
}

/**
 * Completion callback for update queue runner.
 *
 * @param bool $success
 *   Indicates whether or not the batch succeeded.
 * @param array $results
 *   Results of the batch processing.
 * @param array $operations
 *   Reference of all operations performed.
 */
function kcts9_media_manager_batch_video_content_run_finished(
  bool $success,
  array $results,
  array $operations): void {
  /** @var \Drupal\Core\Messenger\Messenger $messenger */
  $messenger = Drupal::service('messenger');

  if ($success) {
    $messenger->addStatus(t('Success! @count Video Content assets have
      been updated from Media Manager.', [
        '@count' => number_format($results['assets_processed']),
      ]));
  }

  foreach ($results['warnings'] as $warning) {
    $messenger->addWarning($warning);
  }
}
