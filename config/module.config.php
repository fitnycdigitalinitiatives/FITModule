<?php
namespace FITModule;

return [
  'media_ingesters' => [
      'invokables' => [
          'iiif' => Media\Ingester\FITModuleIIIF::class,
      ],
  ],
  'media_renderers' => [
      'invokables' => [
          'iiif' => Media\Renderer\FITModuleIIIF::class,
      ],
  ],
];
