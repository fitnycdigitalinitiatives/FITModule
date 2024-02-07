<?php
namespace FITModule\Media\Ingester;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Url as UrlElement;
use Omeka\Stdlib\ErrorStore;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Http\Client as HttpClient;
use Laminas\Uri\Http as HttpUri;

class FITModuleRemoteFile implements MutableIngesterInterface
{
    public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $archival = array_key_exists('archival', $media->mediaData()) ? $media->mediaData()['archival'] : '';
        $replica = array_key_exists('replica', $media->mediaData()) ? $media->mediaData()['replica'] : '';
        $access = array_key_exists('access', $media->mediaData()) ? $media->mediaData()['access'] : '';
        $mets = array_key_exists('mets', $media->mediaData()) ? $media->mediaData()['mets'] : '';
        $thumbnail = array_key_exists('thumbnail', $media->mediaData()) ? $media->mediaData()['thumbnail'] : '';
        $YouTubeID = array_key_exists('YouTubeID', $media->mediaData()) ? $media->mediaData()['YouTubeID'] : '';
        $vimeoID = array_key_exists('vimeoID', $media->mediaData()) ? $media->mediaData()['vimeoID'] : '';
        $GoogleDriveID = array_key_exists('GoogleDriveID', $media->mediaData()) ? $media->mediaData()['GoogleDriveID'] : '';
        return $this->getForm($view, $archival, $replica, $access, $mets, $thumbnail, $YouTubeID, $vimeoID, $GoogleDriveID);
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
        $archival = isset($data['archival']) ? $data['archival'] : '';
        $replica = isset($data['replica']) ? $data['replica'] : '';
        $access = isset($data['access']) ? $data['access'] : '';
        $mets = isset($data['mets']) ? $data['mets'] : '';
        $thumbnail = isset($data['thumbnail']) ? $data['thumbnail'] : '';
        $youtubeID = isset($data['YouTubeID']) ? $data['YouTubeID'] : '';
        $vimeoID = isset($data['vimeoID']) ? $data['vimeoID'] : '';
        $googledriveID = isset($data['GoogleDriveID']) ? $data['GoogleDriveID'] : '';
        // try using YouTube thumbnail if not already available
        if (($thumbnail == '') && ($youtubeID != '')) {
            $thumbnail = sprintf('https://img.youtube.com/vi/%s/hqdefault.jpg', $youtubeID);
        }
        if (($thumbnail == '') && ($vimeoID != '')) {
            $vimeoURL = 'https://vimeo.com/api/oembed.json?url=https://vimeo.com/' . $vimeoID;
            $uri = new HttpUri($vimeoURL);
            $client = new HttpClient;
            $client->reset();
            $client->setUri($uri);
            $response = $client->send();
            if ($response->isOk()) {
                $vimeoData = json_decode($response->getBody(), true);
                if ($vimeoData) {
                    if (array_key_exists("thumbnail_url", $vimeoData)) {
                        $thumbnail = $vimeoData["thumbnail_url"];
                    }
                }
            }
        }
        $mediaData = ['archival' => $archival, 'replica' => $replica, 'access' => $access, 'mets' => $mets, 'thumbnail' => $thumbnail, 'YouTubeID' => $youtubeID, 'vimeoID' => $vimeoID, 'GoogleDriveID' => $googledriveID];
        $media->setData($mediaData);
        // attempt to get MIME for Media Type
        $ext = '';
        if (isset($data['dcterms:identifier'])) {
            //check for service file first
            foreach ($data['dcterms:identifier'] as $key => $value) {
                if (isset($value['o:label']) && ($value['o:label'] == 'service-file')) {
                    $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                    if (($ext == "gz") && substr_compare($value['@id'], ".warc.gz", -strlen(".warc.gz")) === 0) {
                        $ext = "warc.gz";
                    }
                    break;
                }
            }
            //check for original file
            if ($ext == '') {
                foreach ($data['dcterms:identifier'] as $key => $value) {
                    if (isset($value['o:label']) && ($value['o:label'] == 'original-file')) {
                        $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                        if (($ext == "gz") && substr_compare($value['@id'], ".warc.gz", -strlen(".warc.gz")) === 0) {
                            $ext = "warc.gz";
                        }
                        break;
                    }
                }
            }
        }
        if (($access != '') && ($ext == '')) {
            $ext = pathinfo($access, PATHINFO_EXTENSION);
        }
        if ($ext != '') {
            $builder = \Mimey\MimeMappingBuilder::create();
            $builder->add('image/jp2', 'jp2');
            $builder->add('text/vtt', 'vtt');
            $builder->add('application/warc', 'warc');
            $builder->add('application/warc', 'warc.gz');
            $mimes = new \Mimey\MimeTypes($builder->getMapping());
            $media->setMediaType($mimes->getMimeType($ext));
        }
        // probably a video if it has a YouTube/Vimeo/Google Drive id but doesn't have other info
        elseif (($youtubeID != '') || ($vimeoID != '') || ($googledriveID != '')) {
            $media->setMediaType('video');
        } else {
            $media->setMediaType(null);
        }
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        // check to see update is to media itself of media metadata, ie batch edit, only necessary to check for presence of one remote file component
        if (isset($data['o:media']['__index__']['archival'])) {
            // try using YouTube thumbnail if not already available
            if (($data['o:media']['__index__']['thumbnail'] == '') && ($data['o:media']['__index__']['YouTubeID'] != '')) {
                $data['o:media']['__index__']['thumbnail'] = sprintf('http://img.youtube.com/vi/%s/hqdefault.jpg', $data['o:media']['__index__']['YouTubeID']);
            }
            $mediaData = ['archival' => $data['o:media']['__index__']['archival'], 'replica' => $data['o:media']['__index__']['replica'], 'access' => $data['o:media']['__index__']['access'], 'mets' => $data['o:media']['__index__']['mets'], 'thumbnail' => $data['o:media']['__index__']['thumbnail'], 'YouTubeID' => $data['o:media']['__index__']['YouTubeID'], 'vimeoID' => $data['o:media']['__index__']['vimeoID'], 'GoogleDriveID' => $data['o:media']['__index__']['GoogleDriveID']];
            $media->setData($mediaData);
            // attempt to get MIME for Media Type
            $ext = '';
            if (isset($data['dcterms:identifier'])) {
                //check for service file first
                foreach ($data['dcterms:identifier'] as $key => $value) {
                    if (isset($value['o:label']) && ($value['o:label'] == 'service-file')) {
                        $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                        if (($ext == "gz") && substr_compare($value['@id'], ".warc.gz", -strlen(".warc.gz")) === 0) {
                            $ext = "warc.gz";
                        }
                        break;
                    }
                }
                //check for original file
                if ($ext == '') {
                    foreach ($data['dcterms:identifier'] as $key => $value) {
                        if (isset($value['o:label']) && ($value['o:label'] == 'original-file')) {
                            $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                            if (($ext == "gz") && substr_compare($value['@id'], ".warc.gz", -strlen(".warc.gz")) === 0) {
                                $ext = "warc.gz";
                            }
                            break;
                        }
                    }
                }
            }
            if (($data['o:media']['__index__']['access'] != '') && ($ext == '')) {
                $ext = pathinfo($data['o:media']['__index__']['access'], PATHINFO_EXTENSION);
            }
            if ($ext != '') {
                $builder = \Mimey\MimeMappingBuilder::create();
                $builder->add('image/jp2', 'jp2');
                $builder->add('text/vtt', 'vtt');
                $builder->add('application/warc', 'warc');
                $builder->add('application/warc', 'warc.gz');
                $mimes = new \Mimey\MimeTypes($builder->getMapping());
                $media->setMediaType($mimes->getMimeType($ext));
            } elseif ($data['o:media']['__index__']['YouTubeID'] != '') {
                $media->setMediaType('video');
            } else {
                $media->setMediaType(null);
            }
        }
    }

    protected function getForm(PhpRenderer $view, $archival = '', $replica = '', $access = '', $mets = '', $thumb = '', $youtubeID = '', $vimeoID = '', $googledriveID = '')
    {
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

        $metsInput = new UrlElement('o:media[__index__][mets]');
        $metsInput->setOptions([
            'label' => 'METS file URL', // @translate
        ]);
        $metsInput->setAttributes([
            'value' => $mets,
        ]);

        $thumbInput = new UrlElement('o:media[__index__][thumbnail]');
        $thumbInput->setOptions([
            'label' => 'Thumbnail file URL', // @translate
        ]);
        $thumbInput->setAttributes([
            'value' => $thumb,
        ]);

        $youtubeIDInput = new Text('o:media[__index__][YouTubeID]');
        $youtubeIDInput->setOptions([
            'label' => 'YouTube Video ID',
            // @translate
            'info' => 'Can be found in the URL for a video, e.g. for https://www.youtube.com/watch?v=6kCgnnXH2B0 or https://youtu.be/6kCgnnXH2B0 the id is 6kCgnnXH2B0', // @translate
        ]);
        $youtubeIDInput->setAttributes([
            'value' => $youtubeID,
        ]);

        $vimeoIDInput = new Text('o:media[__index__][vimeoID]');
        $vimeoIDInput->setOptions([
            'label' => 'Vimeo Video ID',
            // @translate
            'info' => 'Can be found in the URL for a video, e.g. for https://vimeo.com/manage/videos/711791459 or https://vimeo.com/711791459 the id is 711791459', // @translate
        ]);
        $vimeoIDInput->setAttributes([
            'value' => $vimeoID,
        ]);

        $googledriveIDInput = new Text('o:media[__index__][GoogleDriveID]');
        $googledriveIDInput->setOptions([
            'label' => 'Google Drive Video ID',
            // @translate
            'info' => 'Can be found in the URL for a video on Google Drive, e.g. for https://drive.google.com/file/d/0B4uG-Uwo1YBoeUs1b1JUNTI4WlE/view?usp=sharing or https://drive.google.com/file/d/0B4uG-Uwo1YBoeUs1b1JUNTI4WlE/preview the id is 0B4uG-Uwo1YBoeUs1b1JUNTI4WlE', // @translate
        ]);
        $googledriveIDInput->setAttributes([
            'value' => $googledriveID,
        ]);
        return $view->formRow($archivalInput) . $view->formRow($replicaInput) . $view->formRow($accessInput) . $view->formRow($metsInput) . $view->formRow($thumbInput) . $view->formRow($youtubeIDInput) . $view->formRow($vimeoIDInput) . $view->formRow($googledriveIDInput);
    }
}