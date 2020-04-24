<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class FITModuleRemoteThumbnail implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $RemoteThumbnailURL = $media->source();
        $view->headLink()->appendStylesheet($view->assetUrl('css/openseadragon.css', 'FITModule'));
        $view->headScript()->appendFile('//cdn.jsdelivr.net/npm/openseadragon@2.4/build/openseadragon/openseadragon.min.js');
        $noscript = $view->translate('OpenSeadragon is not available unless JavaScript is enabled.');
        $image =
            '<div class="openseadragon-frame">
              <div class="openseadragon" id="iiif-' . $media->id() . '"></div>
            </div>
            <script type="text/javascript">
                var viewer = OpenSeadragon({
                    id: "iiif-'.$media->id().'",
                    prefixUrl: "https://cdn.jsdelivr.net/npm/openseadragon@2.4/build/openseadragon/images/",
                    tileSources: "'. $IIIFInfoJson .'"
                });
                viewer.addHandler("add-item-failed", function(event) {
                  $("#iiif-' . $media->id() . '").parent().replaceWith("<b>This IIIF resource failed to load. The info.json file may be invalid.</b>");
                });
            </script>
            <noscript>
                <p>' . $noscript . '</p>
            </noscript>'
        ;
        return $image;
    }
}
