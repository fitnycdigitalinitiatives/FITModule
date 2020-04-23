<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class IIIF implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $IIIFInfoJson = $media->source();
        $view->headScript()->appendFile($view->assetUrl('vendor/openseadragon/openseadragon.min.js', 'Omeka'));
        $prefixUrl = $view->assetUrl('vendor/openseadragon/images/', 'Omeka', false, false);
        $noscript = $view->translate('OpenSeadragon is not available unless JavaScript is enabled.');
        $image =
            '<div class="openseadragon" id="iiif-' . $media->id() . '" style="height: 400px;"></div>
            <script type="text/javascript">
                var viewer = OpenSeadragon({
                    id: "iiif-'.$media->id().'",
                    prefixUrl: "'. $prefixUrl . '",
                    showNavigator: true,
                    navigatorSizeRatio: 0.1,
                    minZoomImageRatio: 0.8,
                    maxZoomPixelRatio: 10,
                    controlsFadeDelay: 1000,
                    tileSources: "'. $IIIFInfoJson .'"
                });
            </script>
            <noscript>
                <p>' . $noscript . '</p>
            </noscript>'
        ;
        return $image;
    }
}
