<?php

namespace FITModule\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Laminas\View\Renderer\PhpRenderer;

class DesignersBrowse extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Designer Files Designers Browse'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        return $view->escapeHtml("Designer Files Designers Browse");
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $site = $view->currentSite();
        $response = $view->api()->search('item_sets', [
            'site_id' => $site->id(),
        ]);
        $designers = $response->getContent();
        $totalResults = $response->getTotalResults();
        $designersData = [];
        foreach ($designers as $designer) {
            $designerName = $designer->displayTitle();
            $itemCount = $designer->itemCount();
            $url = $view->url('site/search', ['site-slug' => $site->slug()], ['query' => ['limit' => ['item_set_dcterms_title' => [$designerName]]]]);
            $designersData[strtoupper($designerName[0])][] = ['designerName' => $designerName, 'description' => $designer->displayDescription(), 'nationality' => $designer->value('fitcore:nationality') ? $designer->value('fitcore:nationality')->asHtml() : "", 'itemCount' => $itemCount, 'url' => $url];
        }
        return $view->partial('common/block-layout/designer-files-designer-browse', ['data' => json_encode($designersData), 'totalResults' => $totalResults]);
    }
}
