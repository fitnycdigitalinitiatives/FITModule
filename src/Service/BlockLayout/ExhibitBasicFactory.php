<?php

namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\ExhibitBasic;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExhibitBasicFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ExhibitBasic
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new ExhibitBasic($htmlPurifier);
    }
}
