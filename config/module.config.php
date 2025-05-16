<?php

namespace FITModule;

return [
    'controllers' => [
        'invokables' => [
            'FITModule\Controller\Redirect' => Controller\RedirectController::class,
            'Omeka\Controller\Site\Index' => Controller\Site\IndexController::class,
        ],
        'factories' => [
            'FITModule\Controller\Site\SiteLogin' => Service\Controller\Site\SiteLoginControllerFactory::class,
        ]
    ],
    'data_types' => [
        'invokables' => [
            'uri' => DataType\FITModuleUri::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'browsePreviewCarousel' => Site\BlockLayout\BrowsePreviewCarousel::class,
            'imageVideoTransition' => Site\BlockLayout\ImageVideoTransition::class,
            'itemShowcaseHeroCarousel' => Site\BlockLayout\ItemShowcaseHeroCarousel::class,
            'assetHero' => Site\BlockLayout\AssetHero::class,
            'designersBrowse' => Site\BlockLayout\DesignersBrowse::class,
        ],
        'factories' => [
            'archiveOnDemandAbout' => Service\BlockLayout\ArchiveOnDemandAboutFactory::class,
        ],
    ],
    'media_ingesters' => [
        'invokables' => [
            'remoteFile' => Media\Ingester\FITModuleRemoteFile::class,
            'remoteCompoundObject' => Media\Ingester\FITModuleRemoteCompoundObject::class,
        ],
    ],
    'media_renderers' => [
        'invokables' => [
            'remoteFile' => Media\Renderer\FITModuleRemoteFile::class,
            'remoteCompoundObject' => Media\Renderer\FITModuleRemoteCompoundObject::class,
            'youtube' => Media\Renderer\FITModuleYoutube::class,
        ],
    ],
    'file_renderers' => [
        'invokables' => [
            'pdf' => Media\FileRenderer\PDFRenderer::class,
        ],
        'aliases' => [
            'application/pdf' => 'pdf',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'thumbnail' => View\Helper\FITModuleThumbnail::class,
            's3presigned' => View\Helper\FITModuleS3Presigned::class,
            'miradorViewer' => View\Helper\MiradorViewer::class,
            'itemViewer' => View\Helper\ItemViewer::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'thumbnails' => [
        'fallbacks' => [
            'fallbacks' => [
                'Image' => ['thumbnails/image.png', 'Omeka'],
                'Still Image' => ['thumbnails/image.png', 'Omeka'],
                'Moving Image' => ['thumbnails/video.png', 'Omeka'],
                'Sound' => ['thumbnails/audio.png', 'Omeka'],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'site' => [
                'child_routes' => [
                    'iiif-viewer' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/iiif-viewer',
                            'defaults' => [
                                '__NAMESPACE__' => 'Omeka\Controller',
                                'controller' => 'IiifViewer',
                                'action' => 'index',
                            ],
                        ],
                    ],
                    'redirect' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/items/show/:id',
                            'defaults' => [
                                '__NAMESPACE__' => 'FITModule\Controller',
                                'controller' => 'Redirect',
                                'action' => 'index',
                            ],
                            'constraints' => [
                                'id' => '\d+',
                            ]
                        ],
                    ],
                    'site-login' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/login',
                            'defaults' => [
                                '__NAMESPACE__' => 'FITModule\Controller\Site',
                                'controller' => 'SiteLogin',
                                'action' => 'login',
                            ],
                        ],
                    ],
                    'site-logout' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/logout',
                            'defaults' => [
                                '__NAMESPACE__' => 'FITModule\Controller\Site',
                                'controller' => 'SiteLogin',
                                'action' => 'logout',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
