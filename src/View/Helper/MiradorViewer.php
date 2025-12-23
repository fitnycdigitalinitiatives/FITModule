<?php

namespace FITModule\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Firebase\JWT\JWT;

class MiradorViewer extends AbstractHelper
{
    /**
     * View Helper for rendering Mirador Viewer with authetication option to start at specific canvas
     */
    public function __invoke(AbstractResourceEntityRepresentation $resource, $canvasID = '', array $options = [])
    {
        $view = $this->getView();
        $defaultConfig = [
            'workspace' => [
                'showZoomControls' => true,
            ],
            'workspaceControlPanel' => [
                'enabled' => false,
            ],
            'window' => [
                'hideWindowTitle' => true,
                'allowClose' => false,
                'allowFullscreen' => true,
                'allowMaximize' => false,
                'allowWindowSideBar' => false,
            ],
            'thumbnailNavigation' => [
                'defaultPosition' => 'far-right',
            ],
            'osdConfig' => [
                'preserveViewport' => false,
            ],
            'themes' => [
                'dark' => [
                    'palette' => [
                        'type' => 'dark',
                        'primary' => [
                            'main' => '#0036f9',
                        ],
                        'secondary' => [
                            'main' => '#0036f9',
                        ]
                    ]
                ],
                'light' => [
                    'palette' => [
                        'type' => 'light',
                        'primary' => [
                            'main' => '#0036f9',
                        ],
                        'secondary' => [
                            'main' => '#0036f9',
                        ]
                    ]
                ]
            ]
        ];
        if ($resource instanceof ItemRepresentation) {
            $item = $resource;
            $manifestId = $view->url('iiif-presentation-3/item/manifest', ['item-id' => $item->id()], ['force_canonical' => true]);
            $uniqueID = $item->id();
            $authorization = '';
            $name = "Anonymous";
            $private = false;
            if (!($item->isPublic())) {
                $private = true;
            } else {
                foreach ($item->media() as $media) {
                    if (!($media->isPublic())) {
                        $private = true;
                        break;
                    }
                }
            }
            if ($private) {
                if ($view->identity()) {
                    $name = $view->identity()->getName();
                }
                $secret_key = $view->setting('fit_module_iiif_secret_key');
                $now_seconds = time();
                $payload = array(
                    "iss" => "https://digitalrepository.fitnyc.edu",
                    "iat" => $now_seconds,
                    "exp" => $now_seconds + (60 * 60 * 2),
                    // Maximum expiration time is 2 hours
                    "user" => $name,
                    "visibility" => "private"
                );
                $authorization = JWT::encode($payload, $secret_key, "HS256");
            }
            $canvas = '';
            if ($canvasID) {
                $canvas = $view->url('iiif-presentation-3/item/canvas', ['item-id' => $item->id(), 'media-id' => $canvasID], ['force_canonical' => true]);
            }
        } elseif ($resource instanceof MediaRepresentation) {
            $media = $resource;
            $mediaId = $media->id();
            $item = $media->item();
            $manifestId = $view->url('iiif-presentation-3/media/manifest', ['media-id' => $media->id()], ['force_canonical' => true]);
            if ($media->mediaData()['indexed']) {
                $defaultConfig['window']['sideBarPanel'] = 'search';
                $defaultConfig['window']['allowWindowSideBar'] = true;
                $defaultConfig['window']['panels'] = [
                    'info' => false,
                    'attribution' => false,
                    'canvas' => false,
                    'annotations' => false,
                    'search' => true,
                    'layers' => false,
                ];
            }
            $uniqueID = $mediaId;
            $authorization = '';
            $name = "Anonymous";
            $private = false;
            if (!($item->isPublic())) {
                $private = true;
            } elseif (!($media->isPublic())) {
                $private = true;
            }
            if ($private) {
                if ($view->identity()) {
                    $name = $view->identity()->getName();
                }
                $secret_key = $view->setting('fit_module_iiif_secret_key');
                $now_seconds = time();
                $payload = array(
                    "iss" => "https://digitalrepository.fitnyc.edu",
                    "iat" => $now_seconds,
                    "exp" => $now_seconds + (60 * 60 * 2),
                    // Maximum expiration time is 2 hours
                    "user" => $name,
                    "visibility" => "private"
                );
                $authorization = JWT::encode($payload, $secret_key, "HS256");
            }
            $canvas = '';
            if ($canvasID && ($media->renderer() == 'remoteCompoundObject')) {
                $canvas = $view->url('iiif-presentation-3/media/canvas', ['media-id' => $mediaId, 'index' => $canvasID], ['force_canonical' => true]);
            }
        } else {
            return;
        }
        // Override any default config from $options
        foreach ($options as $key => $subOptions) {
            foreach ($subOptions as $subkey => $value) {
                $defaultConfig[$key][$subkey] = $value;
            }
        }
        $view->headLink()->appendStylesheet($view->assetUrl('css/mirador.css', 'FITModule'));
        $view->headScript()->appendFile('https://unpkg.com/mirador@3.4.3/dist/mirador.min.js', 'text/javascript');
        $view->headScript()->appendFile($view->assetUrl('js/mirador.js', 'FITModule'), 'text/javascript');
        return sprintf('<div class="mirador-viewer-frame"><div class="mirador-viewer" id="mirador-%s" data-manifest="%s" data-authorization="%s" data-canvas="%s" data-options=\'%s\'></div></div>', $uniqueID, $manifestId, $authorization, $canvas, json_encode($defaultConfig));
    }
}
