<?php

namespace FITModule\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\ErrorStore;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Api\Exception\NotFoundException;
use Laminas\View\Renderer\PhpRenderer;

class ItemSetShowcaseHeroCarousel extends AbstractBlockLayout

{
    public function getLabel()
    {
        return 'Collection Showcase Carousel'; // @translate
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('css/item-sets-carousel-form.css', 'FITModule'));
        $view->headScript()->appendFile($view->assetUrl('js/chosen-options.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/item-sets-carousel-form.js', 'FITModule'));
        $view->headScript()->appendFile($view->assetUrl('js/asset-form.js', 'Omeka'));
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        foreach ($data as $key => $value) {
            if (!$value['item_set_id']) {
                $errorStore->addError('item_set_id', 'Each slide must have an item set selected.'); // @translate
                return;
            }
        }
        $block->setData($data);
    }

    public function prepareAssetAttachments(PhpRenderer $view, $blockData)
    {
        $attachments = [];
        if ($blockData) {
            foreach ($blockData as $key => $value) {
                if (isset($value['id'])) {
                    if ($value['id'] !== '') {
                        $assetId = $value['id'];
                        try {
                            $asset = $view->api()->read('assets', $assetId)->getContent();
                            $attachments[$key]['asset'] = $asset;
                        } catch (NotFoundException $e) {
                            $attachments[$key]['asset'] = null;
                        }
                    } else {
                        $attachments[$key]['asset'] = null;
                    }
                    $attachments[$key]['item_set_id'] = $value['item_set_id'];
                    $attachments[$key]['slideTitle'] = $value['slideTitle'];
                    $attachments[$key]['caption'] = $value['caption'];
                }
            }
        }
        return $attachments;
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        $siteId = $site->id();
        $apiUrl = $site->apiUrl();
        $blockData = ($block) ? $block->data() : '';
        $attachments = $this->prepareAssetAttachments($view, $blockData);
        return $view->partial('common/item-sets-attachments-form', [
            'block' => $blockData,
            'siteId' => $siteId,
            'apiUrl' => $apiUrl,
            'attachments' => $attachments,
        ]);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = 'common/block-layout/item-set-showcase-hero-carousel')
    {
        $blockData = ($block) ? $block->data() : '';
        $attachments = $this->prepareAssetAttachments($view, $blockData);
        $siteSlug = $view->site->slug();
        return $view->partial($templateViewScript, [
            'block' => $block,
            'attachments' => $attachments,
            'siteSlug' => $siteSlug,
        ]);
    }
}
