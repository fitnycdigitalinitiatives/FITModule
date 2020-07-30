<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class FITModuleRemoteVideo implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $view->headLink()->appendStylesheet($view->assetUrl('css/video.css', 'FITModule'));
        $youtubeID = $media->mediaData()['YouTubeID'];
        $googledriveID = $media->mediaData()['GoogleDriveID'];
        $accessURL = $media->mediaData()['access'];
        $captions = $media->mediaData()['captions'];
        $thumbnail = $media->mediaData()['thumbnail'];
        if ($youtubeID != '') {
            $url = sprintf('https://www.youtube.com/embed/%s', $youtubeID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-16by9">
                  <iframe class="embed-responsive-item" src="%s" allowfullscreen></iframe>
                </div>',
                $url
            );
            return $embed;
        } elseif ($googledriveID != '') {
            $url = sprintf('https://drive.google.com/file/d/%s/preview', $googledriveID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-16by9">
                  <iframe class="embed-responsive-item" src="%s" allowfullscreen></iframe>
                </div>',
                $url
            );
            return $embed;
        } elseif ($accessURL != '') {
            $videoURL = $view->s3presigned($accessURL);
            if ($captions != '') {
                $captionURL = $view->s3presigned($captions);
                $captionHTML = sprintf(
                    '<track src="%s" kind="captions" label="Captions">',
                    $captionURL
                );
            } else {
                $captionHTML = '';
            }
            $video = sprintf(
                '<div class="embed-responsive embed-responsive-16by9">
                  <video class="embed-responsive-item" controls crossorigin="anonymous">
                    <source src="%s" type="video/mp4">
                    %s
                  </video>
                </div>',
                $videoURL,
                $captionHTML
            );
            return $video;
        } elseif ($thumbnail != '') {
            $image = '<img src="' . $thumbnail . '">';
            return $image;
        }
    }
}
