<?php

namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\WelcomeJumbotron;
use Laminas\ServiceManager\Factory\FactoryInterface;

class WelcomeJumbotronFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return WelcomeJumbotron
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new WelcomeJumbotron($htmlPurifier);
    }
}
