<?php

namespace FITModule\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Stdlib\ErrorStore;
use Omeka\Form\Element\Asset;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;

class ExhibitLanding extends AbstractBlockLayout
{
    /**
     * @var HtmlPurifier
     */
    protected $htmlPurifier;

    public function __construct(HtmlPurifier $htmlPurifier)
    {
        $this->htmlPurifier = $htmlPurifier;
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headScript()->appendFile($view->assetUrl('js/asset-form.js', 'Omeka'));
    }

    public function getLabel()
    {
        return 'Exhibit Landing Page'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $data = $block->getData();
        $html = isset($data['html']) ? $this->htmlPurifier->purify($data['html']) : '';
        $data['html'] = $html;
        $block->setData($data);
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
            'label' => 'Exhibit Title', // @translate
        ]);
        $pageSubtitle = new Element\Text("o:block[__blockIndex__][o:data][pageSubtitle]");
        $pageSubtitle->setOptions([
            'label' => 'Exhibit Subtitle', // @translate
        ]);
        $html = new Element\Textarea("o:block[__blockIndex__][o:data][html]");
        $html->setAttribute('class', 'block-html full wysiwyg');
        $html->setOptions([
            'label' => 'Exhibit Landing Text', // @translate
        ]);
        $backgroundImage = new Asset("o:block[__blockIndex__][o:data][backgroundImage]");
        $backgroundImage->setOptions([
            'label' => 'Background Image', // @translate
            'info' => 'Choose or upload an image to display in backgroud of landing page.', // @translate
        ]);
        $backgroundImage->setAttribute('id', 'backgroundImage');
        $backgroundImage->setAttribute('required', false);
        if ($block) {
            $pageTitle->setValue($block->dataValue('pageTitle'));
            $pageSubtitle->setValue($block->dataValue('pageSubtitle'));
            $html->setValue($block->dataValue('html'));
            $backgroundImage->setValue($block->dataValue('backgroundImage'));
        }
        $form->add($pageTitle);
        $form->add($pageSubtitle);
        $form->add($html);
        $form->add($backgroundImage);

        return $view->formCollection($form);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $htmlBlock = $block->dataValue('html', '');
        $pageTitle = $block->dataValue('pageTitle');
        $pageSubtitle = $block->dataValue('pageSubtitle');
        $backgroundImageURL = null;
        $backgroundImage = $block->dataValue('backgroundImage');
        $backgroundImageAlt = "";
        if ($backgroundImage) {
            $asset = $view->api()->read('assets', $backgroundImage)->getContent();
            $backgroundImageURL = $asset->assetUrl();
            $backgroundImageAlt = $asset->altText();
        }

        return $view->partial('common/block-layout/exhibit-landing', [
            'html' => $htmlBlock,
            'pageTitle' => $pageTitle,
            'pageSubtitle' => $pageSubtitle,
            'backgroundImageURL' => $backgroundImageURL,
            'backgroundImageAlt' => $backgroundImageAlt,
        ]);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return strip_tags($this->render($view, $block));
    }
}
