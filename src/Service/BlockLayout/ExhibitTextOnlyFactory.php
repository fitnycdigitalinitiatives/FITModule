<?php

namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\ExhibitTextOnly;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExhibitTextOnlyFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ExhibitTextOnly
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new ExhibitTextOnly($htmlPurifier);
    }
}
