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
        $image = '<img src="' . $RemoteThumbnailURL . '">';
        return $image;
    }
}
