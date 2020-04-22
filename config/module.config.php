<?php
namespace FITModule;

return [
  'media_ingesters' => [
      'factories' => [
          'iiif' => Service\Media\Ingester\FITModuleIIIFFactory::class,
      ],
  ],
];
