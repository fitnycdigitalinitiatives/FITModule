<?php

namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\ExhibitLanding;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExhibitLandingFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ExhibitLanding
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new ExhibitLanding($htmlPurifier);
    }
}
