<?php

namespace FITModule\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;

class ExhibitSectionPage extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Exhibit Section Page'; // @translate
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        $form = new Form();
        $pageTitle = new Element\Text("o:block[__blockIndex__][o:data][pageTitle]");
        $pageTitle->setOptions([
            'label' => 'Section Title', // @translate
        ]);
        if ($block) {
            $pageTitle->setValue($block->dataValue('pageTitle'));
        }
        $form->add($pageTitle);

        return $view->formCollection($form);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = 'common/block-layout/exhibit-section-page')
    {
        return $view->partial($templateViewScript, [
            'block' => $block,
            'pageTitle' => $block->dataValue('pageTitle'),
        ]);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return strip_tags($this->render($view, $block));
    }
}
