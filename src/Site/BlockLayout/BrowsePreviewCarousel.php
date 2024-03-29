<?php
namespace FITModule\Site\BlockLayout;

use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Form\Element as OmekaElement;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\View\Renderer\PhpRenderer;

class BrowsePreviewCarousel extends AbstractBlockLayout
{
    public function getLabel()
    {
        return 'Browse preview with carousel'; // @translate
    }

    public function prepareForm(PhpRenderer $view)
    {
        $view->headLink()->prependStylesheet($view->assetUrl('css/advanced-search.css', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/advanced-search.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/query-form.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/browse-preview-block-layout.js', 'Omeka'));
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        $defaults = [
            'resource_type' => 'items',
            'query' => '',
            'heading' => '',
            'limit' => 12,
            'filter' => 0,
            'link-text' => 'Browse all', // @translate
            'solr-link' => '',
        ];

        $data = $block ? $block->data() + $defaults : $defaults;

        $form = new Form();
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][resource_type]',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Resource type',
                // @translate
                'value_options' => [
                    'items' => 'Items',
                    // @translate
                    'item_sets' => 'Item sets',
                    // @translate
                    'media' => 'Media',
                    // @translate
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
                'label' => 'Search query',
                // @translate
                'info' => 'Display resources using this search query',
                // @translate
                'query_resource_type' => $data['resource_type'],
                'query_partial_excludelist' => ['common/advanced-search/site'],
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][limit]',
            'type' => Element\Number::class,
            'options' => [
                'label' => 'Limit',
                // @translate
                'info' => 'Maximum number of resources to display in the preview.',
                // @translate
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][filter]',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Include search filter button?',
                // @translate
                'info' => 'If solr search is enabled, include button to filter results. Note: use only once per page or will break the modal',
                // @translate
                'checked_value' => '1',
                'unchecked_value' => '0'
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][heading]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Preview title',
                // @translate
                'info' => 'Heading above resource list, if any.',
                // @translate
            ],
        ]);
        $form->add([
            'name' => 'o:block[__blockIndex__][o:data][link-text]',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Link text',
                // @translate
                'info' => 'Text for link to full browse view, if any.',
                // @translate
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
            'o:block[__blockIndex__][o:data][resource_type]' => $data['resource_type'],
            'o:block[__blockIndex__][o:data][query]' => $data['query'],
            'o:block[__blockIndex__][o:data][heading]' => $data['heading'],
            'o:block[__blockIndex__][o:data][limit]' => $data['limit'],
            'o:block[__blockIndex__][o:data][filter]' => $data['filter'],
            'o:block[__blockIndex__][o:data][link-text]' => $data['link-text'],
            'o:block[__blockIndex__][o:data][solr-link]' => $data['solr-link'],
        ]);

        return $view->formCollection($form);
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
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
            $query['sort_by'] = 'created';
        }
        if (!isset($query['sort_order'])) {
            $query['sort_order'] = 'desc';
        }

        $response = $view->api()->search($resourceType, $query);
        $resources = $response->getContent();

        $resourceTypes = [
            'items' => 'item',
            'item_sets' => 'item-set',
            'media' => 'media',
        ];

        return $view->partial('common/block-layout/browse-preview-carousel', [
            'resourceType' => $resourceTypes[$resourceType],
            'resources' => $resources,
            'heading' => $block->dataValue('heading'),
            'linkText' => $block->dataValue('link-text'),
            'solrLink' => $block->dataValue('solr-link'),
            'filter' => $block->dataValue('filter'),
            'query' => $originalQuery,
            'searchQuery' => $query,
            'site' => $site,
        ]);
    }
}