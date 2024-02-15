<?php
namespace FITModule\Controller;

use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Http\Response;
use Omeka\Mvc\Exception\NotFoundException;

class RedirectController extends AbstractActionController
{
    public function indexAction()
    {
        if ($id = $this->params('id')) {
            $site = $this->currentSite();
            $siteSlug = $site->slug();
            // Check if this is a valid site for a redirect
            if ($siteSlug == 'archiveondemand') {
                switch ($siteSlug) {
                    case 'archiveondemand':
                        $metadataTerm = 'fitcore:aodlegacy';
                        break;
                }
                $api = $this->api();
                $metadataID = $api->searchOne('properties', ['term' => $metadataTerm])->getContent();
                if ($metadataID) {
                    $item = $api->searchOne('items', [
                        'property' => [
                            [
                                'property' => $metadataID->id(),
                                'type' => 'eq',
                                'text' => $id,
                            ],
                        ],
                    ])->getContent();
                    if ($item) {
                        return $this->redirect()->toRoute('site/resource-id', [
                            'site-slug' => $siteSlug,
                            'controller' => 'item',
                            'id' => $item->id(),
                        ])
                            ->setStatusCode(Response::STATUS_CODE_301);
                    } else {
                        throw new NotFoundException("Unable to find this item.");
                    }
                }
            }
        }
        throw new NotFoundException("Invalid Page");
    }
}