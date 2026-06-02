<?php

namespace FITModule\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use FITModule\Site\BlockLayout\ExhibitBrowsePreview;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ExhibitBrowsePreviewFactory implements FactoryInterface
{
    /**
     * Create the Html block layout service.
     *
     * @param ContainerInterface $serviceLocator
     * @return ExhibitBrowsePreview
     */
    public function __invoke(ContainerInterface $serviceLocator, $requestedName, array $options = null)
    {
        $htmlPurifier = $serviceLocator->get('Omeka\HtmlPurifier');
        return new ExhibitBrowsePreview($htmlPurifier);
    }
}
