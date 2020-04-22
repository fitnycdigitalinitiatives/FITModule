<?php
namespace FITModule\Service\Media\Ingester;

use FITModule\Media\Ingester\FITModuleIIIF;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class FITModuleIIIFFactory implements FactoryInterface
{
    /**
     * Create the IIIF media ingester service.
     *
     * @return FITModuleIIIF
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new FITModuleIIIF(
            $services->get('Omeka\HttpClient'),
            $services->get('Omeka\File\Downloader')
        );
    }
}
