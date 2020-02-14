<?php

namespace Drupal\kcts9_media_manager\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\kcts9_media_manager\ApiContentManagerBase;
use Drupal\kcts9_media_manager\ShowManager;
use Drupal\kcts9_media_manager\VideoContentManager;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class NodeUpdateForm.
 *
 * @package Drupal\kcts9_media_manager\Form
 */
class NodeUpdateForm extends FormBase {

  /**
   * Show Manager service.
   *
   * @var \Drupal\kcts9_media_manager\ShowManager
   */
  protected $showManager;

  /**
   * Video Content Manager service.
   *
   * @var \Drupal\kcts9_media_manager\VideoContentManager
   */
  protected $videoContentManager;

  /**
   * Constructs a NodeUpdateForm object.
   *
   * @param \Drupal\kcts9_media_manager\ShowManager $show_manager
   *   Show Manager service.
   * @param \Drupal\kcts9_media_manager\VideoContentManager $video_content_manager
   *   Video Content Manager service.
   */
  public function __construct(
    ShowManager $show_manager,
    VideoContentManager $video_content_manager
  ) {
    $this->showManager = $show_manager;
    $this->videoContentManager = $video_content_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('kcts9_media_manager.show_manager'),
      $container->get('kcts9_media_manager.video_content_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'kcts9_media_manager_node_update';
  }

  /**
   * Restricts access to the either the "show" or "video_content" bundle.
   *
   * Also verifies that the Media Manager API client is configured.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node context.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Allowed if the API client is configured and this is a Show or Video
   *   Content node.
   */
  public function checkAccess(NodeInterface $node): AccessResult {
    return AccessResult::allowedif(
      $this->showManager->getApiClient()->isConfigured()
      && (
        $node->bundle() === ShowManager::getBundleId() ||
        $node->bundle() === VideoContentManager::getBundleId()
      )
    );
  }

  /**
   * Gets page title.
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node context.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   Updated page title with the Node title.
   */
  public function getTitle(NodeInterface $node): TranslatableMarkup {
    return $this->t('Media Manager: @title', ['@title' => $node->getTitle()]);
  }

  /**
   * {@inheritdoc}
   *
   * TODO: Add more useful functionality (e.g. Media Manager lookup).
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    NodeInterface $node = NULL
  ): array {
    $guid = $node->get(ApiContentManagerBase::GUID_FIELD_NAME)->value;

    $item = NULL;
    if (!empty($guid)) {
      if ($node->bundle() === ShowManager::getBundleId()) {
        $item = $this->showManager->getShow($guid);
      }
      elseif ($node->bundle() === VideoContentManager::getBundleId()) {
        $item = $this->videoContentManager->getAsset($guid);
      }
    }

    if (!empty($guid) && empty($item)) {
      $this->messenger()->addWarning($this->t('The GUID associated with this
        Show does not currently exist in Media Manager. The Show cannot be
        updated from Media Manager.'));
    }

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Updating will replace all existing data with
        data from Media Manager.'),
    ];

    $form['guid'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Media Manager GUID'),
      '#value' => $guid,
      '#disabled' => TRUE,
    ];

    if (!empty($item)) {
      $form['media_manager_item'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Media Manager Object Title'),
        '#value' => $item->attributes->title,
        '#disabled' => TRUE,
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => 'Update from Media Manager',
      '#disabled' => empty($guid) || empty($item),
    ];

    $form_state->setStorage([
      'node' => $node,
      'guid' => $guid,
      'item' => $item,
    ]);

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $storage = $form_state->getStorage();
    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage['node'];

    if (!empty($storage['item'])) {
      if ($node->bundle() === ShowManager::getBundleId()) {
        $this->showManager->addOrUpdateShow($storage['item']);
      }
      elseif ($node->bundle() == VideoContentManager::getBundleId()) {
        $this->videoContentManager->addOrUpdateVideoContent(
          $storage['item'],
          NULL,
          TRUE
        );
      }

      $this->messenger()->addStatus($this->t('Node updated from Media 
        Manager!'));

      $form_state->setRedirect('entity.node.edit_form', [
        'node' => $node->id(),
      ]);
    }
    else {
      $this->messenger()->addWarning($this->t('Nothing found to update.'));
    }
  }

}
