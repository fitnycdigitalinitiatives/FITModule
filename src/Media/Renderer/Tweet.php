<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Zend\View\Renderer\PhpRenderer;

class Tweet implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        // Return the oEmbed JSON's "html" value.
        return $media->mediaData()['html'];
    }
}
