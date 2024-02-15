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

class ArchiveOnDemandAbout extends AbstractBlockLayout
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
        return 'Archive on Demand About'; // @translate
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
        $form->add([
            'name' => 'main-about',
            'type' => 'fieldset',
            'options' => [
                'label' => 'About section',
            ],
        ]);
        $form->add([
            'name' => 'contact',
            'type' => 'fieldset',
            'options' => [
                'label' => 'Contact block',
            ],
        ]);
        $form->add([
            'name' => 'record',
            'type' => 'fieldset',
            'options' => [
                'label' => 'Record block',
            ],
        ]);
        $html = new Element\Textarea("o:block[__blockIndex__][o:data][html]");
        $html->setAttribute('class', 'block-html full wysiwyg');
        $pageTitle = new Element\Text("o:block[__blockIndex__][o:data][pageTitle]");
        $pageTitle->setOptions([
            'label' => 'Title of this Page', // @translate
        ]);
        $htmlContact = new Element\Textarea("o:block[__blockIndex__][o:data][htmlContact]");
        $htmlContact->setAttribute('class', 'block-html full wysiwyg');
        $contactTitle = new Element\Text("o:block[__blockIndex__][o:data][contactTitle]");
        $contactTitle->setOptions([
            'label' => 'Title of the contact block', // @translate
        ]);
        $htmlRecord = new Element\Textarea("o:block[__blockIndex__][o:data][htmlRecord]");
        $htmlRecord->setAttribute('class', 'block-html full wysiwyg');
        $recordTitle = new Element\Text("o:block[__blockIndex__][o:data][recordTitle]");
        $recordTitle->setOptions([
            'label' => 'Title of the record block', // @translate
        ]);
        if ($block) {
            $html->setValue($block->dataValue('html'));
            $pageTitle->setValue($block->dataValue('pageTitle'));
            $htmlContact->setValue($block->dataValue('htmlContact'));
            $contactTitle->setValue($block->dataValue('contactTitle'));
            $htmlRecord->setValue($block->dataValue('htmlRecord'));
            $recordTitle->setValue($block->dataValue('recordTitle'));
        }
        $form->get('main-about')->add($html);
        $form->get('main-about')->add($pageTitle);
        $form->get('contact')->add($htmlContact);
        $form->get('contact')->add($contactTitle);
        $form->get('record')->add($htmlRecord);
        $form->get('record')->add($recordTitle);

        return $view->formCollection($form);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $htmlBlock = $block->dataValue('html', '');
        $pageTitle = $block->dataValue('pageTitle', 'About');
        $htmlContactBlock = $block->dataValue('htmlContact', '');
        $contactTitle = $block->dataValue('contactTitle', 'Contact Us');
        $htmlRecordBlock = $block->dataValue('htmlRecord', '');
        $recordTitle = $block->dataValue('recordTitle', 'Record Your Event');

        return $view->partial('common/block-layout/archive-on-demand-about', [
            'html' => $htmlBlock,
            'pageTitle' => $pageTitle,
            'htmlContactBlock' => $htmlContactBlock,
            'contactTitle' => $contactTitle,
            'htmlRecordBlock' => $htmlRecordBlock,
            'recordTitle' => $recordTitle,
        ]);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return strip_tags($this->render($view, $block));
    }
}