<?php

namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\ExhibitBasicGrid;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExhibitBasicGridFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ExhibitBasicGrid
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new ExhibitBasicGrid($htmlPurifier);
    }
}
