<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Request;
use Omeka\Entity\Media;
use Omeka\Media\Ingester\IngesterInterface;
use Omeka\Stdlib\ErrorStore;
use Zend\Form\Element\Url as UrlElement;
use Zend\Uri\Http as HttpUri;
use Zend\View\Renderer\PhpRenderer;

class FITModuleIIIF implements IngesterInterface
{
    public function getLabel()
    {
        return 'IIIF image'; // @translate
    }

    public function getRenderer()
    {
        return 'iiif';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (!isset($data['o:source'])) {
            $errorStore->addError('o:source', 'No IIIF image URL specified');
            return;
        }
        //Skip loading info.json file because we only need to load on render

        // Skip Media Ingest/Thumbnail Creation because we are doing that separately
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        $urlInput = new UrlElement('o:media[__index__][o:source]');
        $urlInput->setOptions([
            'label' => 'IIIF image URL', // @translate
            'info' => 'URL for the image to embed.', // @translate
        ]);
        $urlInput->setAttributes([
            'required' => true,
        ]);
        return $view->formRow($urlInput);
    }

    //Don't need to validate
}
