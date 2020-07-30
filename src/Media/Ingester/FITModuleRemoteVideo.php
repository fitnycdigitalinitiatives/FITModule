<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Zend\Form\Element\Text;
use Zend\Form\Element\Url as UrlElement;
use Omeka\Stdlib\ErrorStore;
use Zend\View\Renderer\PhpRenderer;

class FITModuleRemoteVideo implements MutableIngesterInterface
{
    public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        return $this->getForm($view, $media->mediaData()['YouTubeID'], $media->mediaData()['GoogleDriveID'], $media->mediaData()['master'], $media->mediaData()['access'], $media->mediaData()['thumbnail'], $media->mediaData()['captions']);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view);
    }

    public function getLabel()
    {
        return 'Remote Video'; // @translate
    }

    public function getRenderer()
    {
        return 'remoteVideo';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $youtubeID = isset($data['YouTubeID']) ? $data['YouTubeID'] : '';
        $googledriveID = isset($data['GoogleDriveID']) ? $data['GoogleDriveID'] : '';
        $master = isset($data['master']) ? $data['master'] : '';
        $access = isset($data['access']) ? $data['access'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        if (($thumbnail == '') && ($youtubeID != '')) {
            $thumbnail = sprintf('http://img.youtube.com/vi/%s/hqdefault.jpg', $youtubeID);
        }
        $captions = isset($data['captions']) ? $data['captions'] : '';
        $mediaData = ['YouTubeID' => $youtubeID, 'GoogleDriveID' => $googledriveID, 'master' => $master, 'access' => $access, 'thumbnail' => $thumbnail, 'captions' => $captions];
        $media->setData($mediaData);
        $media->setMediaType('video');
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        if (($data['o:media']['__index__']['thumbnail'] == '') && ($data['o:media']['__index__']['YouTubeID'] != '')) {
            $data['o:media']['__index__']['thumbnail'] = sprintf('http://img.youtube.com/vi/%s/hqdefault.jpg', $data['o:media']['__index__']['YouTubeID']);
        }
        $mediaData = ['YouTubeID' => $data['o:media']['__index__']['YouTubeID'], 'GoogleDriveID' => $data['o:media']['__index__']['GoogleDriveID'], 'master' => $data['o:media']['__index__']['master'], 'access' => $data['o:media']['__index__']['access'], 'thumbnail' => $data['o:media']['__index__']['thumbnail'], 'captions' => $data['o:media']['__index__']['captions']];
        $media->setData($mediaData);
    }

    protected function getForm(PhpRenderer $view, $youtubeID = '', $googledriveID = '', $master = '', $access = '', $thumb = '', $captions = '')
    {
        $youtubeIDInput = new Text('o:media[__index__][YouTubeID]');
        $youtubeIDInput->setOptions([
            'label' => 'YouTube Video ID', // @translate
            'info' => 'Can be found in the URL for a video, e.g. for https://www.youtube.com/watch?v=6kCgnnXH2B0 or https://youtu.be/6kCgnnXH2B0 the id is 6kCgnnXH2B0', // @translate
        ]);
        $youtubeIDInput->setAttributes([
            'value' => $youtubeID,
        ]);

        $googledriveIDInput = new Text('o:media[__index__][GoogleDriveID]');
        $googledriveIDInput->setOptions([
            'label' => 'Google Drive Video ID', // @translate
            'info' => 'Can be found in the URL for a video on Google Drive, e.g. for https://drive.google.com/file/d/0B4uG-Uwo1YBoeUs1b1JUNTI4WlE/view?usp=sharing or https://drive.google.com/file/d/0B4uG-Uwo1YBoeUs1b1JUNTI4WlE/preview the id is 0B4uG-Uwo1YBoeUs1b1JUNTI4WlE', // @translate
        ]);
        $googledriveIDInput->setAttributes([
            'value' => $googledriveID,
        ]);

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

        $captionsInput = new UrlElement('o:media[__index__][captions]');
        $captionsInput->setOptions([
            'label' => 'Captions file URL', // @translate
        ]);
        $captionsInput->setAttributes([
            'value' => $captions,
        ]);
        return $view->formRow($youtubeIDInput) . $view->formRow($googledriveIDInput) . $view->formRow($masterInput) . $view->formRow($accessInput) . $view->formRow($thumbInput) . $view->formRow($captionsInput);
    }
}