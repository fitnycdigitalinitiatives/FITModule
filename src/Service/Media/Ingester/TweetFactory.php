<?php
namespace FITModule\Service\Media\Ingester;

use FITModule\Media\Ingester\Tweet;
use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

class TweetFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Tweet($services->get('Omeka\HttpClient'));
    }
}
