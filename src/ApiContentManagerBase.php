<?php

namespace Drupal\kcts9_media_manager;

use DateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Exception;

/**
 * Class ApiContentManagerBase.
 *
 * @package Drupal\kcts9_media_manager
 */
abstract class ApiContentManagerBase implements ApiContentManagerInterface {
  use StringTranslationTrait;

  /**
   * The Drupal field name for Media Manager GUID.
   */
  const GUID_FIELD_NAME = 'field_remote_content_id';

  /**
   * PBS Media Manager API client wrapper.
   *
   * @var \Drupal\kcts9_media_manager\ApiClient
   *
   * @see \OpenPublicMedia\PbsMediaManager\Client
   */
  protected $client;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * KCTS9 Media Manager logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Queue service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * State interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * ApiContentManagerBase constructor.
   *
   * @param \Drupal\kcts9_media_manager\ApiClient $client
   *   Media Manager API client service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   Logger channel service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service.
   * @param \Drupal\Core\State\StateInterface $state
   *   State service.
   */
  public function __construct(
    ApiClient $client,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelInterface $logger,
    QueueFactory $queue_factory,
    StateInterface $state
  ) {
    $this->client = $client;
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger;
    $this->queueFactory = $queue_factory;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public function getQueue(): QueueInterface {
    return $this->queueFactory->get($this->getQueueName());
  }

  /**
   * {@inheritdoc}
   */
  public function getApiClient(): ApiClient {
    return $this->client;
  }

  /**
   * Gets or creates a node based on API data.
   *
   * @param string $guid
   *   Media Manager GUID of the object to get a Node for.
   * @param string $bundle
   *   The bundle type of the item being retrieved.
   *
   * @return \Drupal\node\NodeInterface
   *   The node to use.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getOrCreateNode(string $guid, string $bundle): NodeInterface {
    $definition = $this->entityTypeManager->getDefinition($this->getEntityTypeId());
    $node = $this->getNodeByGuid($guid, $bundle);

    if (empty($node)) {
      $node = Node::create([
        $definition->getKey('bundle') => $bundle,
      ]);
      $node->enforceIsNew();
    }

    return $node;
  }

  /**
   * Attempts to get a local node by a Media Manager GUID.
   *
   * @param string $guid
   *   Media Manager GUID of the object to get a Node for.
   * @param string $bundle
   *   The bundle type of the item being retrieved.
   *
   * @return \Drupal\node\NodeInterface|null
   *   Related node or NULL if none found.
   */
  public function getNodeByGuid(string $guid, string $bundle): ?NodeInterface {
    try {
      $definition = $this->entityTypeManager->getDefinition($this->getEntityTypeId());
      $storage = $this->entityTypeManager->getStorage($this->getEntityTypeId());
      $nodes = $storage->loadByProperties([
        $definition->getKey('bundle') => $bundle,
        self::GUID_FIELD_NAME => $guid,
      ]);
    }
    catch (Exception $e) {
      // Let NULL fall through.
      $nodes = [];
    }

    $node = NULL;
    if (!empty($nodes)) {
      $node = reset($nodes);
      if (count($nodes) > 1) {
        $this->logger->error('Multiple nodes found for Media Manager
          GUID {guid}. Node IDs found: {nid_list}. Updating node {nid}.', [
            'guid' => $guid,
            'nid_list' => implode(', ', array_keys($nodes)),
            'nid' => $node->id(),
          ]);
      }
    }

    return $node;
  }

  /**
   * Returns the GUID field from a node or NULL if empty.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to evaluate.
   *
   * @return string|null
   *   GUID field value or NULL.
   */
  public static function getNodeGuid(NodeInterface $node): ?string {
    try {
      return $node->get(self::GUID_FIELD_NAME)->value;
    }
    catch (Exception $e) {
      return NULL;
    }
  }

  /**
   * Indicates if a node has a non-empty GUID value.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node to evaluate.
   *
   * @return bool
   *   TRUE if the node has a non-empty GUID field, FALSE otherwise.
   */
  public static function nodeHasGuid(NodeInterface $node): bool {
    return !empty(self::getNodeGuid($node));
  }

  /**
   * Gets a DateTime object without microsecond precision.
   *
   * Media Manager provides microseconds in the `updated_at` field for objects,
   * but Drupal's storage does not record them. This will check for microseconds
   * in a Media Manager date string and remove them if they exist before
   * creating a DateTime object from the string.
   *
   * @param string $datetime
   *   A string representation of datetime in the Media Manager format, either
   *   Y-m-d\TH:i:s\Z or Y-m-d\TH:i:s.u\Z.
   *
   * @return \DateTime|null
   *   DateTime object without microseconds or NULL if creation fails.
   */
  public static function dateTimeNoMicroseconds(string $datetime): ?DateTime {
    // If the format is Y-m-d\TH:i:s.u\Z, the length will be 27 characters and
    // the last eight should be striped to remove microseconds.
    if (strlen($datetime) == 27) {
      $datetime = substr($datetime, 0, -8) . 'Z';
    }
    try {
      $object = new DateTime($datetime);
    }
    catch (Exception $e) {
      $object = NULL;
    }
    return $object;
  }

  /**
   * Convert images array to key by profile and enforce site scheme.
   *
   * This is necessary because some images provided by Media Manager use an
   * "http" scheme. This will cause mixed media errors and prevent images from
   * loading because the website uses an "https" scheme.
   *
   * @param array $images
   *   Images from a Media Manager query.
   * @param string $image_key
   *   Images array key containing the image URL.
   * @param string $profile_key
   *   Images array key containing the image profile string.
   *
   * @return array
   *   All valid images keyed by profile string using a "\\" scheme to match the
   *   site scheme.
   */
  public function parseImages(
    array $images,
    string $image_key = 'image',
    string $profile_key = 'profile'
  ): array {
    $images = array_column($images, $image_key, $profile_key);
    foreach ($images as $key => $image) {
      $parts = parse_url($image);
      if ($parts === FALSE || !isset($parts['host']) || !isset($parts['path'])) {
        unset($images[$key]);
      }
      else {
        $images[$key] = sprintf('//%s%s', $parts['host'], $parts['path']);
      }
    }
    return $images;
  }

  /**
   * Gets latest `updated_at` field from an API response object.
   *
   * This method accounts for the `updated_at` fields in the images array for a
   * an item. These updated dates do not bubble to the top level of the item
   * for some reason. Others, e.g. "availabilities" and "geo" do bubble up.
   *
   * @param object $item
   *   API object.
   *
   * @return \DateTime|null
   *   Latest `updated_at` field value or NULL if DateTime create fails.
   */
  public static function getLatestUpdatedAt(object $item): ?DateTime {
    $updated_at = self::dateTimeNoMicroseconds($item->attributes->updated_at);

    if (isset($item->attributes->images)) {
      foreach ($item->attributes->images as $image) {
        $image_updated_at = self::dateTimeNoMicroseconds($image->updated_at);
        if ($image_updated_at > $updated_at) {
          $updated_at = $image_updated_at;
        }
      }
    }

    return $updated_at;
  }

}
