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
        return $this->getForm($view, $media->mediaData()['preservation'], $media->mediaData()['replica'], $media->mediaData()['access'], $media->mediaData()['thumbnail']);
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
        $preservation = isset($data['preservation']) ? $data['preservation'] : '';
        $replica = isset($data['replica']) ? $data['replica'] : '';
        $access = isset($data['access']) ? $data['access'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $mediaData = ['preservation' => $preservation, 'replica' => $replica, 'access' => $access, 'thumbnail' => $thumbnail];
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
        $mediaData = ['preservation' => $data['o:media']['__index__']['preservation'], 'replica' => $data['o:media']['__index__']['replica'], 'access' => $data['o:media']['__index__']['access'], 'thumbnail' => $data['o:media']['__index__']['thumbnail']];
        $media->setData($mediaData);
        if ($data['o:media']['__index__']['access'] != '') {
            $mimes = new \Mimey\MimeTypes;
            $ext = pathinfo($data['o:media']['__index__']['access'], PATHINFO_EXTENSION);
            $media->setMediaType($mimes->getMimeType($ext));
        } else {
            $media->setMediaType(null);
        }
    }

    protected function getForm(PhpRenderer $view, $preservation = '', $replica = '', $access = '', $thumb = '')
    {
        $preservationInput = new UrlElement('o:media[__index__][preservation]');
        $preservationInput->setOptions([
            'label' => 'Preservation file URL', // @translate
        ]);
        $preservationInput->setAttributes([
            'value' => $preservation,
        ]);

        $replicaInput = new UrlElement('o:media[__index__][replica]');
        $replicaInput->setOptions([
            'label' => 'Replica file URL', // @translate
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
        return $view->formRow($preservationInput) . $view->formRow($replicaInput) . $view->formRow($accessInput) . $view->formRow($thumbInput);
    }
}
