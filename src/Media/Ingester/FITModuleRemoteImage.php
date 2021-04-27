<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Laminas\Form\Element\Url as UrlElement;
use Omeka\Stdlib\ErrorStore;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteImage implements MutableIngesterInterface
{
    public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        return $this->getForm($view, $media->mediaData()['iiif'], $media->mediaData()['archival'], $media->mediaData()['replica'], $media->mediaData()['access'], $media->mediaData()['thumbnail']);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view);
    }

    public function getLabel()
    {
        return 'Remote Image'; // @translate
    }

    public function getRenderer()
    {
        return 'remoteImage';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $iiif = isset($data['iiif']) ? $data['iiif'] : '';
        $archival = isset($data['archival']) ? $data['archival'] : '';
        $replica = isset($data['replica']) ? $data['replica'] : '';
        $access = isset($data['access']) ? $data['access'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $mediaData = ['iiif' => $iiif, 'archival' => $archival, 'replica' => $replica, 'access' => $access, 'thumbnail' => $thumbnail];
        $media->setData($mediaData);
        $media->setMediaType('image');
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $mediaData = ['iiif' => $data['o:media']['__index__']['iiif'], 'archival' => $data['o:media']['__index__']['archival'], 'replica' => $data['o:media']['__index__']['replica'], 'access' => $data['o:media']['__index__']['access'], 'thumbnail' => $data['o:media']['__index__']['thumbnail']];
        $media->setData($mediaData);
    }

    protected function getForm(PhpRenderer $view, $iiif = '', $archival = '', $replica = '', $access = '', $thumb = '')
    {
        $iiifInput = new UrlElement('o:media[__index__][iiif]');
        $iiifInput->setOptions([
            'label' => 'Image IIIF endpoint', // @translate
            'info' => 'URL for info.json file', // @translate
        ]);
        $iiifInput->setAttributes([
            'value' => $iiif,
        ]);

        $archivalInput = new UrlElement('o:media[__index__][archival]');
        $archivalInput->setOptions([
            'label' => 'Archival package URL', // @translate
        ]);
        $archivalInput->setAttributes([
            'value' => $archival,
        ]);

        $replicaInput = new UrlElement('o:media[__index__][replica]');
        $replicaInput->setOptions([
            'label' => 'Replica package URL', // @translate
        ]);
        $replicaInput->setAttributes([
            'value' => $replica,
        ]);

        $accessInput = new UrlElement('o:media[__index__][access]');
        $accessInput->setOptions([
            'label' => 'Access file URL', // @translate
        ]);
        $accessInput->setAttributes([
            'value' => $access,
        ]);

        $thumbInput = new UrlElement('o:media[__index__][thumbnail]');
        $thumbInput->setOptions([
            'label' => 'Thumbnail file URL', // @translate
        ]);
        $thumbInput->setAttributes([
            'value' => $thumb,
        ]);
        return $view->formRow($iiifInput) . $view->formRow($archivalInput) . $view->formRow($replicaInput) . $view->formRow($accessInput) . $view->formRow($thumbInput);
    }
}
