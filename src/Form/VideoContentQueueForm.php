<?php

namespace Drupal\kcts9_media_manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\kcts9_media_manager\ShowManager;
use Drupal\kcts9_media_manager\VideoContentManager;
use Exception;

/**
 * Class VideoContentQueueForm.
 *
 * @package Drupal\kcts9_media_manager\Form
 */
class VideoContentQueueForm extends QueueFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_media_manager_video_content_queue';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['status'] = $this->buildQueueStatusElement($this->videoContentManager);

    $form['batch_update'] = [
      '#type' => 'html_tag',
      '#tag' => 'h2',
      '#value' => $this->t('Batch Update All Video Content'),
    ];

    $form['build'] = [
      '#type' => 'details',
      '#title' => $this->t('Step 1: Build Queue'),
      '#description' => $this->t('Build (or rebuild) the batch update queue.
        Any items currently in this queue will be lost when during queue 
        building. This can generally take 5+ minutes.'),
    ];
    $form['build']['actions']['#type'] = 'actions';
    $form['build']['actions']['build_batch_queue'] = [
      '#type' => 'submit',
      '#name' => 'build_batch_queue',
      '#value' => $this->t('Build batch queue'),
    ];

    $batchQueueSize = $this->queueFactory
      ->get(VideoContentManager::BATCH_QUEUE_NAME)
      ->numberOfItems();

    $form['execute'] = [
      '#type' => 'details',
      '#title' => $this->t('Step 2: Execute Queue'),
      '#description' => $this->t('Execute the items in the batch queue. 
        This will update all existing Video Content nodes with data from Media
        Manager and add any that are missing. The build queue currently has 
        <strong>@count groups of items to be processed</strong>. This can take 
        as long as 3+ hours.', ['@count' => number_format($batchQueueSize)]),
    ];
    $form['execute']['actions']['#type'] = 'actions';
    $form['execute']['actions']['execute_batch_queue'] = [
      '#type' => 'submit',
      '#name' => 'execute_batch_queue',
      '#value' => $this->t('Execute batch queue'),
      '#button_type' => 'primary',
    ];
    if ($batchQueueSize > 0) {
      $form['execute']['actions']['delete_batch_queue'] = [
        '#type' => 'submit',
        '#name' => 'delete_batch_queue',
        '#value' => $this->t('Delete batch queue'),
        '#button_type' => 'danger',
      ];
    }

    $form['build']['#open'] = ($batchQueueSize === 0);
    $form['execute']['#open'] = ($batchQueueSize > 0);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @see kcts9_media_manager_batch_video_content_build()
   * @see kcts9_media_manager_batch_video_content_build_finished()
   * @see kcts9_media_manager_batch_video_content_run()
   * @see kcts9_media_manager_batch_video_content_run_finished()
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();

    switch ($trigger['#name']) {
      /*
       * Populates the queue with Video Content items to be updated.
       */
      case 'update_queue':
        $queue = $this->videoContentManager->getQueue();
        $count_before = $queue->numberOfItems();
        $complete = $this->videoContentManager->updateQueue();
        $count = $queue->numberOfItems() - $count_before;

        if ($complete) {
          $this->messenger()->addStatus($this->t('Queue updated. @count items
            added to queue.', ['@count' => $count]));
        }
        else {
          $this->messenger()->addError($this->t('The queue update process
            did not complete normally. Review site logs for details. @count 
            items added to the queue.', ['@count' => $count]));
        }

        break;

      /*
       * Attempts to run all existing queue items.
       *
       * This process may time out!
       */
      case 'run_queue':
        $count = 0;
        $queue = $this->videoContentManager->getQueue();
        $worker = $this->queueWorkerManager
          ->createInstance(VideoContentManager::getQueueName());
        while ($item = $queue->claimItem()) {
          try {
            $worker->processItem($item->data);
            $queue->deleteItem($item);
            $count++;
          }
          catch (Exception $e) {
            $queue->releaseItem($item);
          }
        }
        $this->messenger()->addStatus($this->t('Queue run complete. @count 
          items processed.', ['@count' => $count]));
        break;

      /*
       * Removes the last update state data and refills the queue with all Shows
       * from Media Manager.
       */
      case 'reset_queue':
        $this->videoContentManager->getQueue()->deleteQueue();
        $this->videoContentManager->resetLastUpdateTime();
        $this->messenger()->addStatus($this->t('Queue reset.'));
        break;

      /*
       * Initiates a batch process that creates a queue of Media Manager API
       * calls to be executed.
       */
      case 'build_batch_queue':
        $definition = $this->entityTypeManager->getDefinition(ShowManager::getEntityTypeId());
        $storage = $this->entityTypeManager->getStorage(ShowManager::getEntityTypeId());
        $nodes = $storage->loadByProperties([
          $definition->getKey('bundle') => ShowManager::getBundleId(),
        ]);

        // Operations are separated in to groups of 10 to save on processing
        // time for each batch item (as services need to initialized each time).
        $groups = array_chunk($nodes, 10);
        $operations = [];
        foreach ($groups as $group) {
          $operations[] = [
            'kcts9_media_manager_batch_video_content_build',
            [$group],
          ];
        }

        $batch = [
          'title' => $this->t('Building Video Content batch queue...'),
          'operations' => $operations,
          'finished' => 'kcts9_media_manager_batch_video_content_build_finished',
          'error_message' => $this->genericBatchErrorMessage(),
          'file' => $this->extensionList->getPath('kcts9_media_manager')
          . '/includes/batch.video_content.builder.inc',
        ];
        batch_set($batch);
        break;

      /*
       * Executes the queue created by the "build_batch_queue" process above.
       * This kicks off the actual Media Manager API calls that must happen to
       * update Video Content nodes locally. Because the queue items cannot
       * safely be claimed/deleted at this point, the batch processing is
       * handling by a single operation that breaks up the API calls.
       */
      case 'execute_batch_queue':
        $batch = [
          'title' => $this->t('Executing Video Content batch queue...'),
          'operations' => [['kcts9_media_manager_batch_video_content_run', []]],
          'finished' => 'kcts9_media_manager_batch_video_content_run_finished',
          'error_message' => $this->genericBatchErrorMessage(),
          'file' => $this->extensionList->getPath('kcts9_media_manager')
          . '/includes/batch.video_content.runner.inc',
        ];
        batch_set($batch);
        break;

      /*
       * Deletes an existing batch queue.
       */
      case 'delete_batch_queue':
        $queue = $this->queueFactory->get(VideoContentManager::BATCH_QUEUE_NAME);
        $queue->deleteQueue();
        $this->messenger()->addStatus('Batch Update queue deleted!');
    }
  }

  /**
   * Returns a generic message about batch update failure.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A generic error message for batch failures.
   */
  private function genericBatchErrorMessage(): TranslatableMarkup {
    return $this->t('A batch error occurred. This is likely the result of
      a code execution time out. Re-run the batch to  continue processing, if 
      necessary.');
  }

}
