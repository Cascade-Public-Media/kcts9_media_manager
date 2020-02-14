<?php

namespace Drupal\kcts9_media_manager\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\kcts9_media_manager\VideoContentManager;

/**
 * Class VideoIsPassportOnlyItemList.
 *
 * @package Drupal\kcts9_media_manager\Plugin\Field\FieldType
 */
class VideoIsPassportOnlyItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $value = FALSE;
    if (!$entity->isNew()) {
      $value = VideoContentManager::videoIsPassportOnly($entity);
      $this->setValue($value);
    }
    $this->list[0] = $this->createItem(0, $value);
  }

}
