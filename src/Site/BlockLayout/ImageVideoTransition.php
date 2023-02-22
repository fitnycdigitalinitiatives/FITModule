<?php
namespace FITModule\Site\BlockLayout;

use Omeka\Api\Exception as ApiException;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element\Select;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;

class ImageVideoTransition extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Image to Video Transition'; // @translate
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headScript()->appendFile($view->assetUrl('js/asset-form.js', 'Omeka'));
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $block->setData($data);
    }

    public function prepareAssetAttachments(PhpRenderer $view, $blockData, SiteRepresentation $site)
    {
        $attachments = [];
        $sitePages = $site->pages();
        $sitePageArray = [];
        foreach ($sitePages as $sitePage) {
            $sitePageArray[$sitePage->id()] = $sitePage;
        }
        if ($blockData) {
            foreach ($blockData as $key => $value) {
                if (isset($value['id'])) {
                    if ($value['id'] !== '') {
                        $assetId = $value['id'];
                        try {
                            $asset = $view->api()->read('assets', $assetId)->getContent();
                            $attachments[$key]['asset'] = $asset;
                        } catch (ApiException\NotFoundException $e) {
                            $attachments[$key]['asset'] = null;
                        }
                    } else {
                        $attachments[$key]['asset'] = null;
                    }
                    if ($value['page'] !== '') {
                        $linkPageId = $value['page'];
                        $attachments[$key]['page'] = (isset($sitePageArray[$linkPageId])) ? $sitePageArray[$linkPageId] : null;
                    }
                    $attachments[$key]['alt_link_title'] = $value['alt_link_title'];
                    $attachments[$key]['caption'] = $value['caption'];
                }
            }
        }
        return $attachments;
    }

    public function form(
        PhpRenderer $view, SiteRepresentation $site, SitePageRepresentation $page = null, SitePageBlockRepresentation $block = null
    )
    {
        $siteId = $site->id();
        $apiUrl = $site->apiUrl();
        $blockData = ($block) ? $block->data() : '';
        $attachments = $this->prepareAssetAttachments($view, $blockData, $site);
        return $view->partial('common/image-video-transition-block-form', [
            'block' => $blockData,
            'siteId' => $siteId,
            'apiUrl' => $apiUrl,
            'attachments' => $attachments,
        ]);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $blockData = ($block) ? $block->data() : '';
        $site = $view->site;
        $attachments = $this->prepareAssetAttachments($view, $blockData, $site);
        $videoURL = $block->dataValue('videoURL');
        $itemLinkId = $block->dataValue('itemLinkId');
        $caption = $block->dataValue('caption');
        $customClass = $block->dataValue('className');
        return $view->partial('common/block-layout/image-video-transition', [
            'attachments' => $attachments,
            'videoURL' => $videoURL,
            'itemLinkId' => $itemLinkId,
            'caption' => $caption,
            'className' => $customClass,
        ]);
    }
}