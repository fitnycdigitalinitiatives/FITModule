<?php
namespace FITModule;

return [
  'media_ingesters' => [
      'invokables' => [
          'image' => Media\Ingester\FITModuleImage::class,
      ],
  ],
  'media_renderers' => [
      'invokables' => [
          'image' => Media\Renderer\FITModuleImage::class,
      ],
  ],
  'view_helpers' => [
    'invokables' => [
        'thumbnail' => View\Helper\FITModuleThumbnail::class,
    ],
  ],
  'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
