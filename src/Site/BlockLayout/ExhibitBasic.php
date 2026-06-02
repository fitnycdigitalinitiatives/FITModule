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

class ExhibitBasic extends AbstractBlockLayout
{
    /**
     * @var HtmlPurifier
     */
    protected $htmlPurifier;

    public function __construct(HtmlPurifier $htmlPurifier)
    {
        $this->htmlPurifier = $htmlPurifier;
    }

    public function getLabel()
    {
        return 'Exhibit Basic Page with Text and Media'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $pageText = isset($data['pageText']) ? $this->htmlPurifier->purify($data['pageText']) : '';
        $data['pageText'] = $pageText;
        $block->setData($data);
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        ?SitePageRepresentation $page = null,
        ?SitePageBlockRepresentation $block = null
    ) {
        $pageTitle = new Element\Text('o:block[__blockIndex__][o:data][pageTitle]');
        $pageTitle->setOptions([
            'label' => 'Page Title', // @translate
        ]);
        $pageTitle->setValue($block ? $block->dataValue('pageTitle') : '');

        $pageText = new Element\Textarea("o:block[__blockIndex__][o:data][pageText]");
        $pageText->setAttribute('class', 'block-html full wysiwyg');
        $pageText->setOptions([
            'label' => 'Page Text', // @translate
        ]);
        $pageText->setValue($block ? $block->dataValue('pageText') : '');

        $html = '';
        $html .= $view->blockAttachmentsForm($block);
        $html .= $view->formRow($pageTitle);
        $html .= $view->formRow($pageText);
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = 'common/block-layout/exhibit-basic')
    {
        $attachments = $block->attachments();
        if (!$attachments) {
            return '';
        }
        foreach ($attachments as $key => $attachment) {
            if (!$attachment->item()) {
                unset($attachments[$key]);
            }
        }

        $pageTitle = $block->dataValue('pageTitle');
        $pageText = $block->dataValue('pageText');

        return $view->partial($templateViewScript, [
            'block' => $block,
            'attachments' => $attachments,
            'pageTitle' => $pageTitle,
            'pageText' => $pageText,
        ]);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return strip_tags($this->render($view, $block));
    }
}
