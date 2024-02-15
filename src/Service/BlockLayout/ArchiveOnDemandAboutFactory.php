<?php
namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\ArchiveOnDemandAbout;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ArchiveOnDemandAboutFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ArchiveOnDemandAbout
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new ArchiveOnDemandAbout($htmlPurifier);
    }
}