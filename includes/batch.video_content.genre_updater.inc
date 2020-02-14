<?php

/**
 * @file
 * Batch callback for updating Video Content editorial genres in batch.
 */

use Drupal\kcts9_media_manager\VideoContentManager;
use Drupal\node\NodeInterface;

/**
 * Runs the editorial genre update batch.
 *
 * @param \Drupal\node\NodeInterface $show
 *   Show to update all children Video Content nodes for.
 * @param array $add
 *   Genres to add to each Video Content node (ids).
 * @param array $remove
 *   Genres to remove from each Video Content node (ids).
 * @param array $context
 *   Batch processing context information.
 *
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function kcts9_media_manager_batch_genre_updater_run(
  NodeInterface $show,
  array $add,
  array $remove,
  array &$context
): void {
  $sandbox = &$context['sandbox'];
  $results = &$context['results'];
  /** @var \Drupal\Core\Entity\EntityStorageInterface $storage */
  $storage = Drupal::service('entity_type.manager')
    ->getStorage(VideoContentManager::getEntityTypeId());

  if (empty($sandbox)) {
    $sandbox['nids'] = $storage->getQuery()
      ->condition('type', VideoContentManager::getBundleId())
      ->condition('field_show_ref', $show->id())
      ->execute();
    $results['processed'] = 0;
    $sandbox['total'] = count($sandbox['nids']);
  }

  // Get 100 nodes from the list to process based on how many nodes have already
  // been processed.
  $slice = array_slice($sandbox['nids'], $results['processed'], 100);
  /** @var \Drupal\node\NodeInterface[] $nodes */
  $nodes = $storage->loadMultiple($slice);

  foreach ($nodes as $node) {
    // Get term IDs for genres in a simple array.
    $values = $node->get('field_editorial_genre')->getValue();
    array_walk($values, function (&$value) {
      return $value = $value['target_id'];
    });

    // Remove and add genres by term ID.
    $values_new = array_diff($values, $remove);
    $values_new = array_unique(array_merge($values_new, $add));

    // Compare changes with existing values and update node if necessary.
    sort($values);
    sort($values_new);
    if ($values != $values_new) {
      $node->set('field_editorial_genre', $values_new);
      $node->save();
    }
    $results['processed']++;
  }

  if (isset($node)) {
    $context['message'] = t('@processed of @total nodes processed. Last:
      @title (@id)', [
        '@processed' => number_format($results['processed']),
        '@total' => number_format($sandbox['total']),
        '@title' => $node->label(),
        '@id' => $node->id(),
      ]);
  }

  // Update progress of batch run.
  if ($results['processed'] != $sandbox['total']) {
    $context['finished'] = $results['processed'] / $sandbox['total'];
  }
}

/**
 * Completion callback for genre update batch.
 *
 * @param bool $success
 *   Indicates whether or not the batch succeeded.
 * @param array $results
 *   Results of the batch processing.
 * @param array $operations
 *   Reference of all operations performed.
 */
function kcts9_media_manager_batch_genre_updater_run_finished(
  bool $success,
  array $results,
  array $operations
): void {
  /** @var \Drupal\Core\Messenger\Messenger $messenger */
  $messenger = Drupal::service('messenger');

  if ($success) {
    $messenger->addStatus(t('@count Video Content nodes have been updated
      to reflect changes to the Show editorial genre(s).', [
        '@count' => number_format($results['processed']),
      ]));
  }
}
