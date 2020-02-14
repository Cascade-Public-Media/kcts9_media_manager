<?php

namespace Drupal\kcts9_media_manager\EventSubscriber;

use Drupal\kcts9_media_manager\ShowManager;
use Drupal\kcts9_media_manager\VideoContentManager;
use Drupal\scheduler\SchedulerEvent;
use Drupal\scheduler\SchedulerEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Subscribe to Scheduler module events.
 *
 * @see \Drupal\scheduler\SchedulerEvents
 *
 * @ingroup kcts9_media_manager
 */
class SchedulerEventSubscriber implements EventSubscriberInterface {

  /**
   * Show manager service.
   *
   * @var \Drupal\kcts9_media_manager\ShowManager
   */
  protected $showManager;

  /**
   * SchedulerEventSubscriber constructor.
   *
   * @param \Drupal\kcts9_media_manager\ShowManager $show_manager
   *   Show manager service.
   */
  public function __construct(ShowManager $show_manager) {
    $this->showManager = $show_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[SchedulerEvents::PUBLISH][] = ['onPublish'];
    $events[SchedulerEvents::UNPUBLISH][] = ['onUnpublish'];
    return $events;
  }

  /**
   * Publish a Video Content node's related Show node if possible.
   *
   * The related Show will be published if it is not currently published and the
   * node is _publishable_.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   Scheduler event with affected node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *
   * @see \Drupal\kcts9_media_manager\ShowManager::showIsPublishable()
   */
  public function onPublish(SchedulerEvent $event) {
    $node = $event->getNode();
    if ($node->bundle() === VideoContentManager::getBundleId()) {
      /** @var \Drupal\node\NodeInterface $show */
      $show = $node->get('field_show_ref')->entity;
      if ($show && !$show->isPublished() && $show->get('publishable')->value) {
        $show->setPublished();
        $show->save();
      }
    }
  }

  /**
   * Unpublish related Show node if the last Video Content node is unpublished.
   *
   * @param \Drupal\scheduler\SchedulerEvent $event
   *   Scheduler event with affected node.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onUnpublish(SchedulerEvent $event) {
    $node = $event->getNode();
    if ($node->bundle() === VideoContentManager::getBundleId()) {
      /** @var \Drupal\node\NodeInterface $show */
      $show = $node->get('field_show_ref')->entity;
      if ($show && $show->isPublished()) {
        $video_nids = $this->showManager->getVideoContent($show, TRUE);
        if (empty($video_nids)) {
          $show->setUnpublished();
          $show->save();
        }
      }
    }
  }

}
