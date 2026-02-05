<?php

namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteCompoundObject implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $canvasID = null;
        $defaultSearchQuery = '';
        if (($params = $view->params()->fromQuery()) && array_key_exists('page_id', $params) && ($page_id = $params['page_id']) && array_key_exists('media_id', $params) && ($media_id = $params['media_id']) && ($media_id == $media->id())) {
            $index_offset = is_numeric($media->mediaData()['index_offset']) ? $media->mediaData()['index_offset'] : 0;
            $canvasID = (int) preg_replace('/[^0-9]/', '', $page_id) + $index_offset;
            if (array_key_exists('ocr_text', $params) && $params['ocr_text']) {
                $defaultSearchQuery = $params['ocr_text'];
            }
        }
        return $view->miradorViewer($media, $canvasID, [], $defaultSearchQuery);
    }
}
