<?php

namespace Drupal\kcts9_media_manager\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\kcts9_media_manager\ShowManager;
use Exception;

/**
 * Class ShowsQueueForm.
 *
 * @package Drupal\kcts9_media_manager\Form
 */
class ShowsQueueForm extends QueueFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_media_manager_shows_queue';
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['status'] = $this->buildQueueStatusElement($this->showManager);
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $queue = $this->showManager->getQueue();

    switch ($trigger['#name']) {
      /*
       * Populates the queue with Show items to be updated.
       */
      case 'update_queue':
        $count_before = $queue->numberOfItems();
        $this->showManager->updateQueue();
        $count = $queue->numberOfItems() - $count_before;
        $this->messenger()->addStatus($this->t('Queue updated. @count items
          added to queue.', ['@count' => $count]));
        break;

      /*
       * Attempts to run all existing queue items.
       *
       * This process may time out!
       */
      case 'run_queue':
        $count = 0;
        $worker = $this->queueWorkerManager->createInstance(ShowManager::getQueueName());
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
        $queue->deleteQueue();
        $this->showManager->resetLastUpdateTime();
        $this->messenger()->addStatus($this->t('Queue reset.'));
        break;

    }
  }

}
