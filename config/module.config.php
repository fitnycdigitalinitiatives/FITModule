<?php
namespace FITModule;

return [
  'media_ingesters' => [
      'invokables' => [
          'iiif' => Media\Ingester\FITModuleIIIF::class,
          'remote_thumbnail' => Media\Ingester\FITModuleRemoteThumbnail::class,
      ],
  ],
  'media_renderers' => [
      'invokables' => [
          'iiif' => Media\Renderer\FITModuleIIIF::class,
          'remote_thumbnail' => Media\Renderer\FITModuleRemoteThumbnail::class,
      ],
  ],
];
