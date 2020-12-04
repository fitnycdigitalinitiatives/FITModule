<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Laminas\Form\Element\Url as UrlElement;
use Omeka\Stdlib\ErrorStore;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteFile implements MutableIngesterInterface
{
    public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        return $this->getForm($view, $media->mediaData()['master'], $media->mediaData()['access'], $media->mediaData()['thumbnail']);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view);
    }

    public function getLabel()
    {
        return 'Remote File'; // @translate
    }

    public function getRenderer()
    {
        return 'remoteFile';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $master = isset($data['master']) ? $data['master'] : '';
        $access = isset($data['access']) ? $data['access'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $mediaData = ['master' => $master, 'access' => $access, 'thumbnail' => $thumbnail];
        $media->setData($mediaData);
        if ($access != '') {
            $mimes = new \Mimey\MimeTypes;
            $ext = pathinfo($access, PATHINFO_EXTENSION);
            $media->setMediaType($mimes->getMimeType($ext));
        } else {
            $media->setMediaType(null);
        }
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $mediaData = ['master' => $data['o:media']['__index__']['master'], 'access' => $data['o:media']['__index__']['access'], 'thumbnail' => $data['o:media']['__index__']['thumbnail']];
        $media->setData($mediaData);
        if ($data['o:media']['__index__']['access'] != '') {
            $mimes = new \Mimey\MimeTypes;
            $ext = pathinfo($data['o:media']['__index__']['access'], PATHINFO_EXTENSION);
            $media->setMediaType($mimes->getMimeType($ext));
        } else {
            $media->setMediaType(null);
        }
    }

    protected function getForm(PhpRenderer $view, $master = '', $access = '', $thumb = '')
    {
        $masterInput = new UrlElement('o:media[__index__][master]');
        $masterInput->setOptions([
            'label' => 'Master file URL', // @translate
        ]);
        $masterInput->setAttributes([
            'value' => $master,
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
        return $view->formRow($masterInput) . $view->formRow($accessInput) . $view->formRow($thumbInput);
    }
}
