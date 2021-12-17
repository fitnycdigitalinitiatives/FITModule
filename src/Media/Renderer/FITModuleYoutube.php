<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Laminas\Uri\Http as HttpUri;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleYoutube implements RendererInterface
{
    const WIDTH = 420;
    const HEIGHT = 315;
    const ALLOWFULLSCREEN = true;

    public function render(
        PhpRenderer $view,
        MediaRepresentation $media,
        array $options = []
    ) {
        if (!isset($options['width'])) {
            $options['width'] = self::WIDTH;
        }
        if (!isset($options['height'])) {
            $options['height'] = self::HEIGHT;
        }
        if (!isset($options['allowfullscreen'])) {
            $options['allowfullscreen'] = self::ALLOWFULLSCREEN;
        }

        // Compose the YouTube embed URL and build the markup.
        $view->headLink()->appendStylesheet($view->assetUrl('css/audioVideo.css', 'FITModule'));
        $data = $media->mediaData();
        $url = new HttpUri(sprintf('https://www.youtube.com/embed/%s', $data['id']));
        $query = [];
        if (isset($data['start'])) {
            $query['start'] = $data['start'];
        }
        if (isset($data['end'])) {
            $query['end'] = $data['end'];
        }
        $url->setQuery($query);
        $embed = sprintf(
            '<div class="embed-responsive embed-responsive-16by9"><iframe src="%s" frameborder="0"%s></iframe></div>',
            $view->escapeHtml($url),
            $options['allowfullscreen'] ? ' allowfullscreen' : ''
        );
        return $embed;
    }
}
