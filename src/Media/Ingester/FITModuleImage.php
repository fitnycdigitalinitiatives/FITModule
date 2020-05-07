<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Zend\Form\Element\Url as UrlElement;
use Omeka\Stdlib\ErrorStore;
use Zend\View\Renderer\PhpRenderer;

class FITModuleImage implements MutableIngesterInterface
{
    public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        return $this->getForm($view, $media->mediaData()['IIIF'], $media->mediaData()['master'], $media->mediaData()['access'], $media->mediaData()['thumbnail']);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view);
    }

    public function getLabel()
    {
        return 'Image'; // @translate
    }

    public function getRenderer()
    {
        return 'image';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $iiif = isset($data['IIIF']) ? $data['IIIF'] : '';
        $master = isset($data['master']) ? $data['master'] : '';
        $access = isset($data['access']) ? $data['access'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $mediaData = ['IIIF' => $iiif, 'master' => $master, 'access' => $access, 'thumbnail' => $thumbnail];
        $media->setData($mediaData);
        $media->setMediaType('image');
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $mediaData = ['IIIF' => $data['o:media']['__index__']['IIIF'], 'master' => $data['o:media']['__index__']['master'], 'access' => $data['o:media']['__index__']['access'], 'thumbnail' => $data['o:media']['__index__']['thumbnail']];
        $media->setData($mediaData);
    }

    protected function getForm(PhpRenderer $view, $iiif = '', $master = '', $access = '', $thumb = '')
    {
        $iiifInput = new UrlElement('o:media[__index__][IIIF]');
        $iiifInput->setOptions([
            'label' => 'Image IIIF endpoint', // @translate
            'info' => 'URL for info.json file', // @translate
        ]);
        $iiifInput->setAttributes([
            'value' => $iiif,
        ]);

        $masterInput = new UrlElement('o:media[__index__][master]');
        $masterInput->setOptions([
            'label' => 'Image master copy URL', // @translate
        ]);
        $masterInput->setAttributes([
            'value' => $master,
        ]);

        $accessInput = new UrlElement('o:media[__index__][access]');
        $accessInput->setOptions([
            'label' => 'Image access copy URL', // @translate
        ]);
        $accessInput->setAttributes([
            'value' => $access,
        ]);

        $thumbInput = new UrlElement('o:media[__index__][thumbnail]');
        $thumbInput->setOptions([
            'label' => 'Image thumbnail copy URL', // @translate
        ]);
        $thumbInput->setAttributes([
            'value' => $thumb,
        ]);
        return $view->formRow($iiifInput) . $view->formRow($masterInput) . $view->formRow($accessInput) . $view->formRow($thumbInput);
    }
}
