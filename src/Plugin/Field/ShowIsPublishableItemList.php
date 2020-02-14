<?php

namespace Drupal\kcts9_media_manager\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\kcts9_media_manager\ShowManager;

/**
 * Class ShowIsPublishableItemList.
 *
 * @package Drupal\kcts9_media_manager\Plugin\Field\FieldType
 */
class ShowIsPublishableItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *
   * @see \Drupal\kcts9_media_manager\ShowManager::showIsPublishable()
   */
  protected function computeValue() {
    $entity = $this->getEntity();
    $value = FALSE;
    if (!$entity->isNew()) {
      $value = ShowManager::showIsPublishable($entity);
      $this->setValue($value);
    }
    $this->list[0] = $this->createItem(0, $value);
  }

}
