<?php

namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteCompoundObject implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        return $view->miradorViewer($media, null, ['window' => [
            'hideWindowTitle' => true,
        ]]);
    }
}
