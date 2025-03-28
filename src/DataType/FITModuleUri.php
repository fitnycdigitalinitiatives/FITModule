<?php

namespace FITModule\DataType;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Representation\ValueRepresentation;
use Omeka\DataType\AbstractDataType;
use Omeka\DataType\ValueAnnotatingInterface;
use Omeka\Entity\Value;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleUri extends AbstractDataType implements ValueAnnotatingInterface
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
        return $view->partial('common/data-type/uri');
    }

    public function isValid(array $valueObject)
    {
        if (
            isset($valueObject['@id'])
            && is_string($valueObject['@id'])
            && '' !== trim($valueObject['@id'])
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
        $uriLabel = $value->value();
        $hyperlink = $view->plugin('hyperlink');
        if (filter_var($uri, FILTER_VALIDATE_URL)) {
            if (!$uriLabel) {
                $uriLabel = $uri;
            }
            if ($view->currentSite() && (str_contains($uri, "id.loc.gov/authorities") || str_contains($uri, "vocab.getty.edu/aat") || str_contains($uri, "vocab.getty.edu/page") || str_contains($uri, "getty.edu/vow"))) {
                return $uriLabel;
            } else {
                $icon = '<i class="fas fa-external-link-alt" aria-hidden="true" title="External link"></i>';
                return $uriLabel . $hyperlink->raw($icon, $uri, ['style' => 'margin-left:.5em', 'target' => '_blank', 'aria-label' => 'External link']);
            }
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

    public function valueAnnotationPrepareForm(PhpRenderer $view) {}

    public function valueAnnotationForm(PhpRenderer $view)
    {
        return $view->partial('common/data-type/value-annotation-uri');
    }
}
