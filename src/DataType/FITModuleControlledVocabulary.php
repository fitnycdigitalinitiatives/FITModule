<?php
namespace FITModule\DataType;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\AbstractDataType;
use Omeka\Entity\Value;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use Omeka\Permissions\Acl;
use Zend\Mvc\MvcEvent;

class FITModuleControlledVocabulary extends AbstractDataType
{
    public function getName()
    {
        return 'controlled_vocabulary';
    }
    public function getLabel()
    {
        return 'Controlled Vocabulary'; // @translate
    }
    public function form(PhpRenderer $view)
    {
        return $view->partial('common/data-type/FITModuleControlledVocabulary');
    }
    public function isValid(array $valueObject)
    {
        if (isset($valueObject['@id'])
          && is_string($valueObject['@id'])
          && '' !== trim($valueObject['@id'])
      ) {
            return true;
        }
        if (isset($valueObject['o:label'])
            && is_string($valueObject['o:label'])
            && '' !== trim($valueObject['o:label'])
        ) {
            return true;
        }
        return false;
    }
    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter)
    {
        $value->setUri($valueObject['@id']);
        if (isset($valueObject['o:label'])) {
            $value->setValue($valueObject['o:label']);
        } else {
            $value->setValue(null); // set default
        }
        $value->setLang(null); // set default
        $value->setValueResource(null); // set default
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        $uri = $value->uri();
        $label = $value->value();
        if (!$uri) {
            $searchTerm = $label;
        } else {
            $searchTerm = $uri;
        }

        $propertyId = $value->property()->id();
        $admin = $view->status()->isAdminRequest();
        $site = $view->status()->isSiteRequest();
        //setup the route params to pass to the Url helper. Both the route name and its parameters go here
        $routeParams = [
                'action' => 'browse',
        ];
        $controllerName = $value->resource()->getControllerName();
        $routeParams['controller'] = $controllerName;
        if ($admin) {
            $routeParams['route'] = 'admin/default';
        } elseif ($site) {
            $routeParams['route'] = 'site';
            $params = $view->params()->fromRoute();
            $routeParams['site-slug'] = $params['site-slug'] . '/' . $controllerName;
        } else {
            return;
        }

        $url = $view->plugin('Url');
        $escape = $view->plugin('escapeHtml');
        $hyperlink = $view->plugin('hyperlink');

        $searchUrl = $url(
            $routeParams['route'],
            $routeParams,
            ['query' => ['Search' => '',
                    'property[0][property]' => $propertyId,
                    'property[0][type]' => 'eq',
                    'property[0][text]' => $searchTerm,
                ],
            ]
        );

        if (!$uri) {
            return $label . $hyperlink->raw('&nbsp;<i class="fas fa-search" title="Search by this term"><span class="sr-only">Search by this term</span></i>', $searchUrl, ['class' => 'metadata-browse-direct-link']);
        } else {
            return $label . $hyperlink->raw('&nbsp;<i class="fas fa-search" title="Search by this term"><span class="sr-only">Search by this term</span></i>', $searchUrl, ['class' => 'metadata-browse-direct-link']) . $hyperlink->raw('&nbsp;<i class="fas fa-info-circle" title="Source URI"><span class="sr-only">Source URI</span></i>', $uri, ['class' => 'uri-value-link', 'target' => '_blank']);
        }
    }

    public function getJsonLd(ValueRepresentation $value)
    {
        $jsonLd = ['@id' => $value->uri()];
        if ($value->value()) {
            $jsonLd['o:label'] = $value->value();
        }
        return $jsonLd;
    }

    public function getFulltextText(PhpRenderer $view, ValueRepresentation $value)
    {
        return sprintf('%s %s', $value->uri(), $value->value());
    }
}
