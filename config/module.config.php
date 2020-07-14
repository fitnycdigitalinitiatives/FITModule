<?php
namespace FITModule;

return [
  'data_types' => [
      'invokables' => [
          'uri' => DataType\FITModuleUri::class,
          'controlled_vocabulary' => DataType\FITModuleControlledVocabulary::class,
      ],
  ],
  'media_ingesters' => [
      'invokables' => [
          'remoteImage' => Media\Ingester\FITModuleRemoteImage::class,
      ],
  ],
  'media_renderers' => [
      'invokables' => [
          'remoteImage' => Media\Renderer\FITModuleRemoteImage::class,
      ],
  ],
  'view_helpers' => [
    'invokables' => [
        'thumbnail' => View\Helper\FITModuleThumbnail::class,
        's3presigned' => View\Helper\FITModuleS3Presigned::class,
    ],
  ],
  'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
