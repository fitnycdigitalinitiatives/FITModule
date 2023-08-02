<?php
namespace FITModule\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ItemRepresentation;
use Firebase\JWT\JWT;

class MiradorViewer extends AbstractHelper
{
    /**
     * View Helper for rendering Mirador Viewer with authetication option to start at specific canvas
     */
    public function __invoke(ItemRepresentation $item, $canvasMediaID = '', array $options = [])
    {
        $view = $this->getView();
        $view->headLink()->appendStylesheet($view->assetUrl('css/mirador.css', 'FITModule'));
        $view->headScript()->appendFile('https://unpkg.com/mirador@3.3.0/dist/mirador.min.js', 'text/javascript');
        $view->headScript()->appendFile($view->assetUrl('js/mirador.js', 'FITModule'), 'text/javascript');
        $manifestId = $view->url('iiif-presentation-3/item/manifest', ['item-id' => $item->id()], ['force_canonical' => true]);
        $authorization = '';
        $name = "Anonymous";
        if (!($item->isPublic())) {
            if ($view->identity()) {
                $name = $view->identity()->getName();
            }
            $secret_key = $view->setting('fit_module_iiif_secret_key');
            $now_seconds = time();
            $payload = array(
                "iss" => "https://digitalrepository.fitnyc.edu",
                "iat" => $now_seconds,
                "exp" => $now_seconds + (60 * 60),
                // Maximum expiration time is one hour
                "user" => $name,
                "visibility" => "private"
            );
            $authorization = JWT::encode($payload, $secret_key, "HS256");
        }
        $canvas = '';
        if ($canvasMediaID) {
            $canvas = $view->url('iiif-presentation-3/item/canvas', ['item-id' => $item->id(), 'media-id' => $canvasMediaID], ['force_canonical' => true]);
        }
        return sprintf('<div id="mirador-viewer-frame"><div id="mirador-viewer" data-manifest="%s" data-authorization="%s" data-canvas="%s"></div></div>', $manifestId, $authorization, $canvas);
    }
}