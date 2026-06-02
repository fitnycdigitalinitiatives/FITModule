<?php

namespace FITModule\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Site\BlockLayout\TemplateableBlockLayoutInterface;
use Omeka\Entity\SitePageBlock;
use Omeka\Stdlib\HtmlPurifier;
use Omeka\Stdlib\ErrorStore;
use Laminas\Form\Element;
use Omeka\Form\Element as OmekaElement;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;

class ExhibitBrowsePreview extends AbstractBlockLayout implements TemplateableBlockLayoutInterface
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
        $view->headLink()->prependStylesheet($view->assetUrl('css/advanced-search.css', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/advanced-search.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/query-form.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/browse-preview-block-layout.js', 'Omeka'));
    }

    public function getLabel()
    {
        return 'Exhibit Browse Preview'; // @translate
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
        $defaults = [
            'pageTitle' => '',
            'pageText' => '',
            'resource_type' => 'items',
            'query' => '',
            'limit' => 12,
            'link-text' => 'Browse all', // @translate
            'solr-link' => '',
        ];

        $data = $block ? $block->data() + $defaults : $defaults;

        $form = new Form();
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][pageTitle]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Page Title', // @translate
            ]
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][pageText]',
            'type' => Element\Textarea::class,
            'attributes' => [
                'class' => 'block-html full wysiwyg'
            ],
            'options' => [
                'label' => 'Page Text', // @translate
            ]
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][resource_type]',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Resource type', // @translate
                'value_options' => [
                    'items' => 'Items',  // @translate
                    'item_sets' => 'Item sets',  // @translate
                    'media' => 'Media',  // @translate
                ],
            ],
            'attributes' => [
                'class' => 'browse-preview-resource-type',
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][query]',
            'type' => OmekaElement\Query::class,
            'options' => [
                'label' => 'Search query', // @translate
                'info' => 'Display resources using this search query', // @translate
                'query_resource_type' => $data['resource_type'],
                'query_partial_excludelist' => ['common/advanced-search/site'],
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][limit]',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Limit', // @translate
                'info' => 'Maximum number of resources to display in the preview.', // @translate
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][link-text]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Link text', // @translate
                'info' => 'Text for link to full browse view, if any.', // @translate
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][solr-link]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Custom solr search link',
                // @translate
                'info' => 'Link to solr search results instead of default system search. Can be use with search by property so filtering is possible.',
                // @translate
            ],
        ]);

        $form->setData([
            'o:block[__blockIndex__][o:data][pageTitle]' => $data['pageTitle'],
            'o:block[__blockIndex__][o:data][pageText]' => $data['pageText'],
            'o:block[__blockIndex__][o:data][resource_type]' => $data['resource_type'],
            'o:block[__blockIndex__][o:data][query]' => $data['query'],
            'o:block[__blockIndex__][o:data][limit]' => $data['limit'],
            'o:block[__blockIndex__][o:data][link-text]' => $data['link-text'],
            'o:block[__blockIndex__][o:data][solr-link]' => $data['solr-link'],
        ]);

        return $view->formCollection($form);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block, $templateViewScript = 'common/block-layout/exhibit-browse-preview')
    {
        $pageTitle = $block->dataValue('pageTitle');
        $pageText = $block->dataValue('pageText');
        $resourceType = $block->dataValue('resource_type', 'items');

        parse_str($block->dataValue('query'), $query);
        $originalQuery = $query;

        $site = $block->page()->site();
        if ($view->siteSetting('browse_attached_items', false)) {
            $query['site_attachments_only'] = true;
        }

        $query['site_id'] = $site->id();
        $query['limit'] = $block->dataValue('limit', 12);

        if (!isset($query['sort_by'])) {
            $query['sort_by_default'] = '';
            $query['sort_by'] = 'created';
        }
        if (!isset($query['sort_order'])) {
            $query['sort_order_default'] = '';
            $query['sort_order'] = 'desc';
        }
        $response = $view->api()->search($resourceType, $query);
        $resources = $response->getContent();

        $resourceTypes = [
            'items' => 'item',
            'item_sets' => 'item-set',
            'media' => 'media',
        ];

        return $view->partial($templateViewScript, [
            'block' => $block,
            'pageTitle' => $pageTitle,
            'pageText' => $pageText,
            'resourceType' => $resourceTypes[$resourceType],
            'resources' => $resources,
            'linkText' => $block->dataValue('link-text'),
            'query' => $originalQuery,
            'solrLink' => $block->dataValue('solr-link'),
        ]);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        return strip_tags($this->render($view, $block));
    }
}
