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
        } elseif ($thumbnail != '') {
            $image = '<img src="' . $thumbnail . '">';
            return $image;
        }
    }
}
