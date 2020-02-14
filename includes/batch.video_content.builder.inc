<?php

/**
 * @file
 * Batch callbacks for the Video Content queue builder.
 */

use Drupal\kcts9_media_manager\VideoContentManager;

/**
 * Batch callback for the update queue builder.
 *
 * Builds a queue of array tuples with a "show-id" key containing the Media
 * Manager GUID for a show and a "page" key with a page number (one tuple for
 * each page of results the API claims to have). These values will be used by
 * the accompanying execute batch to make the actual API calls and process the
 * returned data.
 *
 * @param \Drupal\node\NodeInterface[] $nodes
 *   A group of nodes to be processed.
 * @param array $context
 *   Context for the batch processing.
 *
 * @see \Drupal\kcts9_media_manager\Form\VideoContentQueueForm::submitForm()
 * @see \Drupal\kcts9_media_manager\VideoContentManager::executeBatchUpdateQueue()
 */
function kcts9_media_manager_batch_video_content_build(
  array $nodes,
  array &$context
): void {
  /** @var \Drupal\kcts9_media_manager\ApiClient $apiClient */
  $apiClient = Drupal::service('kcts9_media_manager.api_client');
  /** @var \Drupal\Core\Queue\QueueInterface $queue */
  $queue = Drupal::service('queue')->get(VideoContentManager::BATCH_QUEUE_NAME);

  // Empty results signifies first item of the batch.
  if (empty($context['results'])) {
    $context['results']['assets_queued'] = 0;
    $context['results']['warnings'] = [];

    // This queue should only ever be used for one batch, so at the start it
    // is deleted (to clear any existing items) and recreated.
    $queue->deleteQueue();
    $queue = Drupal::service('queue')->get(VideoContentManager::BATCH_QUEUE_NAME);
  }

  foreach ($nodes as $node) {
    // Verify that the Show node has a GUID. Give up if it does not.
    $guid = $node->get(VideoContentManager::GUID_FIELD_NAME)->value;
    if (!$guid) {
      $context['results']['warnings'][] = t(
        'No GUID associated with Show @show (@nid)',
        ['@show' => $node->label(), '@nid' => $node->id()]
      );
      return;
    }

    // Get a count of total assets and pages that will need to be batched.
    try {
      $results = $apiClient->getAssets(['show-id' => $guid]);
      $asset_count = count($results);
      $response = $results->getResponse();
      $page_count = count($response);
    }
    catch (Exception $e) {
      // Allow exceptions to fall through (e.g. a Show has been deplatformed).
      watchdog_exception('kcts9_media_manager', $e);
      $asset_count = 0;
      $page_count = 0;
    }

    // Add all pages to batch.
    for ($i = 1; $i <= $page_count; $i++) {
      $queue->createItem(['show-id' => $guid, 'page' => $i]);
    }

    $context['results']['assets_queued'] += $asset_count;
  }

  if (isset($node)) {
    $context['message'] = t('Last Show node processed: @label (@id)', [
      '@label' => $node->label(),
      '@id' => $node->id(),
    ]);
  }
}

/**
 * Completion callback for update queue builder.
 *
 * @param bool $success
 *   Indicates whether or not the batch succeeded.
 * @param array $results
 *   Results of the batch processing.
 * @param array $operations
 *   Reference of all operations performed.
 */
function kcts9_media_manager_batch_video_content_build_finished(
  bool $success,
  array $results,
  array $operations
): void {
  /** @var \Drupal\Core\Messenger\Messenger $messenger */
  $messenger = Drupal::service('messenger');

  if ($success) {
    $messenger->addStatus(t('Success! @count Video Content assets have
      been queued for update from Media Manager.', [
        '@count' => number_format($results['assets_queued']),
      ]));
  }

  foreach ($results['warnings'] as $warning) {
    $messenger->addWarning($warning);
  }
}
