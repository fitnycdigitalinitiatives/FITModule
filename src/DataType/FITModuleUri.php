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
        return $view->partial('common/data-type/uri');
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
        $resource = $value->resource();
        $property = $value->property();
        $subpropertiesHTML = '';
        $entityManager = $value->getServiceLocator()->get('Omeka\EntityManager');
        if ($resource->id() && $property->id()) {
            $dql = 'SELECT a FROM Attributes\Entity\Attributes a WHERE a.item = :item AND a.property = :property AND a.uriMatch = :uriMatch';
            $query = $entityManager->createQuery($dql)->setParameters(array(
                'item' => $resource->id(),
                'property' => $property->id(),
                'uriMatch' => $uri,
            ));
            $attributes = $query->getResult();
            if ($attributes) {
                $subpropertiesHTML = '<div class="attributes">';
                foreach ($attributes as $key => $attribute) {
                    foreach ($attribute->getData() as $subproperty => $subvalue) {
                        $subpropertiesHTML .= '<div class="subproperty"><h5>' . $subproperty . '</h5><div class="value">' . $subvalue . '</div></div>';
                    }
                }
                $subpropertiesHTML .= '</div>';
            }
        }
        //search attribute table for same item id and property and matching value

        /*if ($entity->getId()) {
            $dql = sprintf(
                'SELECT n FROM %s n WHERE n.resource = :resource',
                $dataType->getEntityClass()
            );
            $query = $em->createQuery($dql);
            $query->setParameter('resource', $entity);
            $existingNumbers = $query->getResult();
        }*/

        if (filter_var($uri, FILTER_VALIDATE_URL)) {
            if (!$uriLabel) {
                $uriLabel = $uri;
            }
            return $view->hyperlink($uriLabel, $uri, ['class' => 'uri-value-link', 'target' => '_blank']) . $subpropertiesHTML;
        } else {
            if (!$uriLabel) {
                return $uri;
            } else {
                return $uriLabel . ': ' . $uri . $subpropertiesHTML;
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
