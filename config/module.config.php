<?php
namespace FITModule;

return [
  'data_types' => [
      'invokables' => [
          'uri' => DataType\FITModuleUri::class,
      ],
  ],
  'media_ingesters' => [
      'invokables' => [
          'remoteFile' => Media\Ingester\FITModuleRemoteFile::class,
      ],
  ],
  'media_renderers' => [
      'invokables' => [
          'remoteFile' => Media\Renderer\FITModuleRemoteFile::class,
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
    ],
  ],
  'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
];
