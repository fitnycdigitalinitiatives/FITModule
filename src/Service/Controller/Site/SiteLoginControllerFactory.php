<?php

namespace FITModule\Service\Controller\Site;

use Interop\Container\ContainerInterface;
use FITModule\Controller\Site\SiteLoginController;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SiteLoginControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SiteLoginController(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\AuthenticationService')
        );
    }
}
