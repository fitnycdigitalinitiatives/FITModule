<?php
namespace FITModule\DataType;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\AbstractDataType;
use Omeka\Entity\Value;
use Zend\View\Renderer\PhpRenderer;

class FITModuleUri extends AbstractDataType
{
    public function getName()
    {
        return 'uri';
    }

    public function getLabel()
    {
        return 'URI'; // @translate
    }

    public function form(PhpRenderer $view)
    {
        return $view->partial('common/data-type/FITModuleUri');
    }

    public function isValid(array $valueObject)
    {
        if (isset($valueObject['@id'])
            && is_string($valueObject['@id'])
            && '' !== trim($valueObject['@id'])
        ) {
            return true;
        }
        return false;
    }

    public function hydrate(array $valueObject, Value $value, AbstractEntityAdapter $adapter)
    {
        //Label is required, URI is additional so that can be added without one if necessary
        $value->setValue($valueObject['o:label']);
        if (isset($valueObject['@id'])) {
            $value->setUri($valueObject['@id']);
        } else {
            $value->setUri(null); // set default
        }
        $value->setLang(null); // set default
        $value->setValueResource(null); // set default
    }

    public function render(PhpRenderer $view, ValueRepresentation $value)
    {
        $uri = $value->uri();
        $uriLabel = $value->value();
        if (filter_var($uri, FILTER_VALIDATE_URL)) {
            if (!$uriLabel) {
                $uriLabel = $uri;
            }
            return $view->hyperlink($uriLabel, $uri, ['class' => 'uri-value-link', 'target' => '_blank']);
        } else {
            if (!$uriLabel) {
                return $uri;
            } else {
                return $uriLabel . ': ' . $uri;
            }
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