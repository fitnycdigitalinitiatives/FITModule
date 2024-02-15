<?php
/**
 * FITModule
 */
namespace FITModule;

use Omeka\Module\AbstractModule;
use Laminas\View\Renderer\PhpRenderer;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use FITModule\Form\ConfigForm;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Exception\DynamoDbException;

class Module extends AbstractModule
{
    /** Module body **/

    /** Load AWS SDK **/
    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
        $events = $moduleManager->getEventManager();

        // Registering a listener at default priority, 1, which will trigger
        // after the ConfigListener merges config.
        $events->attach(ModuleEvent::EVENT_MERGE_CONFIG, array($this, 'onMergeConfig'));
    }


    // Need to unmerge thumbnail fallbacks
    public function onMergeConfig(ModuleEvent $e)
    {
        $configListener = $e->getConfigListener();
        $config = $configListener->getMergedConfig(false);

        // Modify the configuration; here, we'll remove a specific key:
        if (isset($config['thumbnails']['fallbacks']['default']) && (count($config['thumbnails']['fallbacks']['default']) > 2)) {
            $config['thumbnails']['fallbacks']['default'] = array_slice($config['thumbnails']['fallbacks']['default'], 2);
        }
        if (isset($config['thumbnails']['fallbacks']['fallbacks']['image']) && (count($config['thumbnails']['fallbacks']['fallbacks']['image']) > 2)) {
            $config['thumbnails']['fallbacks']['fallbacks']['image'] = array_slice($config['thumbnails']['fallbacks']['fallbacks']['image'], 2);
        }
        if (isset($config['thumbnails']['fallbacks']['fallbacks']['video']) && (count($config['thumbnails']['fallbacks']['fallbacks']['video']) > 2)) {
            $config['thumbnails']['fallbacks']['fallbacks']['video'] = array_slice($config['thumbnails']['fallbacks']['fallbacks']['video'], 2);
        }
        if (isset($config['thumbnails']['fallbacks']['fallbacks']['audio']) && (count($config['thumbnails']['fallbacks']['fallbacks']['audio']) > 2)) {
            $config['thumbnails']['fallbacks']['fallbacks']['audio'] = array_slice($config['thumbnails']['fallbacks']['fallbacks']['audio'], 2);
        }

        // Pass the changed configuration back to the listener:
        $configListener->setMergedConfig($config);
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);

        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl->allow(
            null,
            [
                'FITModule\Controller\Redirect',
            ]
        );
    }

    /**
     * Get this module's configuration array.
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $form = new ConfigForm;
        $form->init();
        $form->setData([
            's3_connection' => $settings->get('fit_module_s3_connection'),
            'aws_key' => $settings->get('fit_module_aws_key'),
            'aws_secret_key' => $settings->get('fit_module_aws_secret_key'),
            'aws_iiif_endpoint' => $settings->get('fit_module_aws_iiif_endpoint'),
            'iiif_secret_key' => $settings->get('fit_module_iiif_secret_key'),
            'aws_dynamodb_table' => $settings->get('fit_module_aws_dynamodb_table'),
            'aws_dynamodb_table_region' => $settings->get('fit_module_aws_dynamodb_table_region'),
        ]);
        return $renderer->formCollection($form);
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        $form = new ConfigForm;
        $form->init();
        $form->setData($controller->params()->fromPost());
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }
        $formData = $form->getData();
        $settings->set('fit_module_s3_connection', $formData['s3_connection']);
        $settings->set('fit_module_aws_key', $formData['aws_key']);
        $settings->set('fit_module_aws_secret_key', $formData['aws_secret_key']);
        $settings->set('fit_module_aws_iiif_endpoint', $formData['aws_iiif_endpoint']);
        $settings->set('fit_module_iiif_secret_key', $formData['iiif_secret_key']);
        $settings->set('fit_module_aws_dynamodb_table', $formData['aws_dynamodb_table']);
        $settings->set('fit_module_aws_dynamodb_table_region', $formData['aws_dynamodb_table_region']);
        return true;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            [$this, 'displayRemoteMetadataSidebar']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ItemRepresentation',
            'rep.resource.json',
            [$this, 'attachRemoteMetadataJson']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ItemSetRepresentation',
            'rep.resource.json',
            [$this, 'attachRemoteMetadataJson']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Representation\MediaRepresentation',
            'rep.resource.json',
            [$this, 'attachRemoteMetadataJson']
        );
        // Add relators to contributor values
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ValueRepresentation',
            'rep.value.html',
            [$this, 'contributorRelators'],
            -1
        );
        // Remove relators from annotations so they are not duplicated
        $sharedEventManager->attach(
            'Omeka\Api\Representation\ValueAnnotationRepresentation',
            'rep.resource.value_annotation_display_values',
            [$this, 'removeContributorRelators']
        );
        // Add twitter and facebook meta
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.show.before',
            [$this, 'addSocialMeta']
        );
        // Update DynamoDB Table with item/media visibility
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.create.post',
            [$this, 'updateVisibility']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.create.post',
            [$this, 'updateVisibility']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.update.post',
            [$this, 'updateVisibility']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.update.post',
            [$this, 'updateVisibility']
        );
        // Update iiif presentation thumbnail
        $sharedEventManager->attach(
            'IiifPresentation\v3\Controller\ItemController',
            'iiif_presentation.3.item.manifest',
            [$this, 'updateIiif3ThumbnailRights']
        );
        $sharedEventManager->attach(
            'IiifPresentation\v2\Controller\ItemController',
            'iiif_presentation.2.item.manifest',
            [$this, 'updateIiif2ThumbnailRights']
        );
        // Hide items not on site
        // $sharedEventManager->attach(
        //     'Omeka\Controller\Site\Item',
        //     'view.show.before',
        //     [$this, 'hideItemsOnSite']
        // );
    }

    public function displayRemoteMetadataSidebar(Event $event)
    {
        if (($event->getTarget()->media->ingester() == 'remoteImage') || ($event->getTarget()->media->ingester() == 'remoteVideo') || ($event->getTarget()->media->ingester() == 'remoteFile')) {
            $view = $event->getTarget();
            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()->appendStylesheet($assetUrl('css/FITModuleMoreMediaMeta.css', 'FITModule'));
            $view->headScript()->appendFile($assetUrl('js/FITModuleS3Presigned.js', 'FITModule'), 'text/javascript', ['defer' => 'defer']);
            $view->headScript()->appendFile('https://cdn.jsdelivr.net/npm/clipboard@2.0.6/dist/clipboard.min.js', 'text/javascript', ['defer' => 'defer']);
            echo $event->getTarget()->partial('common/more-media-meta');
        }
    }
    public function attachRemoteMetadataJson(Event $event)
    {
        $resource = $event->getTarget();
        $primaryMedia = $resource->primaryMedia();
        if ($primaryMedia) {
            if ($primaryMedia->ingester() == 'remoteFile') {
                $thumbnailURL = $primaryMedia->mediaData()['thumbnail'];
                if ($thumbnailURL) {
                    $jsonLd = $event->getParam('jsonLd');
                    $thumbnail_data['medium'] = $thumbnailURL;
                    $jsonLd['thumbnail_display_urls'] = $thumbnail_data;
                    if ($jsonLd['@type'] == 'o:Media') {
                        $jsonLd['o:thumbnail_urls'] = $thumbnail_data;
                    }
                    $event->setParam('jsonLd', $jsonLd);
                }
            }
        }
    }

    /**
     * Add the relators data to the resource's display values.
     *
     * Event $event
     */
    public function contributorRelators(Event $event)
    {
        // Check if this is a site request
        $routeMatch = $this->getServiceLocator()->get('Application')->getMvcEvent()->getRouteMatch();
        if ($routeMatch->getParam('__SITE__')) {
            $value = $event->getTarget();
            // Only check values that are dcterms:contributor
            if ($value->property()->term() == "dcterms:contributor") {
                if ($valueAnnotation = $value->valueAnnotation()) {
                    foreach ($valueAnnotation->values() as $term => $propertyData) {
                        if ($propertyData['property']->term() == "bf:role") {
                            $relatorList = [];
                            $relatorString = '';
                            foreach ($propertyData['values'] as $annotationValue) {
                                array_push($relatorList, $annotationValue);
                            }
                            if ($relatorList) {
                                $relatorString = ' (' . implode(", ", $relatorList) . ')';
                                $params = $event->getParams();
                                $html = $params['html'];
                                // case with no link tags
                                if (strpos($html, "<a") === false) {
                                    $html = $html . $relatorString;
                                }
                                // case where value is inside link tag
                                elseif (strpos($html, "<a") == 0) {
                                    // resource links need to be inside of span because of icon
                                    if ($value->type() == "resource") {
                                        $pos = strpos($html, "</span>");
                                        $html = substr_replace($html, $relatorString, $pos, 0);
                                    } else {
                                        $pos = strpos($html, "</a>");
                                        $html = substr_replace($html, $relatorString, $pos, 0);
                                    }
                                }
                                // case where value proceeds link tags
                                elseif ($pos = strpos($html, "<a")) {
                                    $html = substr_replace($html, $relatorString, $pos, 0);
                                }
                                $event->setParam('html', "$html");
                            }
                        }
                    }
                }
            }
        }
    }

    public function removeContributorRelators(Event $event)
    {
        // Check if this is a site request
        $routeMatch = $this->getServiceLocator()->get('Application')->getMvcEvent()->getRouteMatch();
        if ($routeMatch->getParam('__SITE__')) {
            $values = $event->getParam('values');
            unset($values["bf:role"]);
            $event->setParam('values', $values);
        }
    }

    public function addSocialMeta(Event $event)
    {
        $item = $event->getTarget()->item;
        $view = $event->getTarget();
        $escape = $view->plugin('escapeHtml');
        $view->headMeta()->setProperty('og:url', $escape($item->url()));
        $view->headMeta()->setProperty('og:title', $item->displayTitle());
        $view->headMeta()->setProperty('og:type', 'article');
        if ($item->thumbnail()) {
            $view->headMeta()->setProperty('og:image', $item->thumbnail()->assetUrl());
            $view->headMeta()->setProperty('twitter:image', $item->thumbnail()->assetUrl());
        } elseif (($primaryMedia = $item->primaryMedia()) && ($primaryMedia->ingester() == 'remoteFile') && ($thumbnailURL = $primaryMedia->mediaData()['thumbnail'])) {
            $view->headMeta()->setProperty('og:image', $thumbnailURL);
            $view->headMeta()->setProperty('twitter:image', $thumbnailURL);
        } elseif (($primaryMedia = $item->primaryMedia()) && ($primaryMedia->thumbnailUrl('medium'))) {
            $view->headMeta()->setProperty('og:image', $thumbnailURL);
            $view->headMeta()->setProperty('twitter:image', $thumbnailURL);
        }
        if ($item->value('dcterms:contributor')) {
            $view->headMeta()->setProperty('og:article:author', $item->value('dcterms:contributor'));
        }
        if ($item->value('dcterms:abstract')) {
            $view->headMeta()->setProperty('og:description', $item->value('dcterms:abstract'));
            $view->headMeta()->setProperty('twitter:description', $item->value('dcterms:abstract'));
        }
        $view->headMeta()->setProperty('twitter:card', 'summary');
        $view->headMeta()->setProperty('twitter:site', '@FITLibrary');
        $view->headMeta()->setProperty('twitter:title', $item->displayTitle());
    }

    /**
     * Update item/media visibility in DynamoDB table.
     *
     * @param Event $event
     */
    public function updateVisibility(Event $event)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        if (($table = $settings->get('fit_module_aws_dynamodb_table')) && ($region = $settings->get('fit_module_aws_dynamodb_table_region')) && ($key = $settings->get('fit_module_aws_key')) && ($secret = $settings->get('fit_module_aws_secret_key'))) {
            $thisResourceAdapter = $event->getTarget();
            $response = $event->getParam('response');
            $entity = $response->getContent();
            $representation = $thisResourceAdapter->getRepresentation($entity);
            if ($representation->getControllerName() == 'item') {
                $item = $representation;
                if ($item->media()) {
                    $mediaSet = $item->media();
                } else {
                    //There isn't any media to worry about
                    return;
                }
            } elseif ($representation->getControllerName() == 'media') {
                $mediaSet = array($representation);
                $item = $representation->item();
            }
            $client = new DynamoDbClient([
                'credentials' => [
                    'key' => $key,
                    'secret' => $secret,
                ],
                'region' => $region,
                'version' => 'latest'
            ]);
            foreach ($mediaSet as $media) {
                if (($media->ingester() == 'remoteFile') && ($accessURL = $media->mediaData()['access'])) {
                    $parsed_url = parse_url($accessURL);
                    $key = ltrim($parsed_url["path"], '/');
                    $extension = pathinfo($key, PATHINFO_EXTENSION);
                    if ($extension == 'tif') {
                        if (($item->isPublic()) && ($media->isPublic())) {
                            //public
                            try {
                                $response = $client->putItem(
                                    array(
                                        'TableName' => $table,
                                        'Item' => array(
                                            'key' => array('S' => $key),
                                            'visibility' => array('S' => 'public')
                                        )
                                    )
                                );
                                if ($response['@metadata']['statusCode'] != 200) {
                                    throw new \Exception("Unable to write visibility to DynamoDB for " . $key . ". Please contact an administrator. Status code: " . $response['@metadata']['statusCode'], 1);
                                }
                            } catch (DynamoDbException $e) {
                                throw new \Exception("Unable to write visibility to DynamoDB for " . $key . ". Please contact an administrator. " . $e->getMessage(), 1);
                            }
                        } else {
                            //private
                            try {
                                $response = $client->putItem(
                                    array(
                                        'TableName' => $table,
                                        'Item' => array(
                                            'key' => array('S' => $key),
                                            'visibility' => array('S' => 'private')
                                        )
                                    )
                                );
                                if ($response['@metadata']['statusCode'] != 200) {
                                    throw new \Exception("Unable to write visibility to DynamoDB for " . $key . ". Please contact an administrator. Status code: " . $response['@metadata']['statusCode'], 1);
                                }
                            } catch (DynamoDbException $e) {
                                throw new \Exception("Unable to write visibility to DynamoDB for " . $key . ". Please contact an administrator. " . $e->getMessage(), 1);
                            }
                        }
                    }
                }
            }
        }
    }

    public function updateIiif3ThumbnailRights(Event $event)
    {
        $changed = false;
        $manifest = $event->getParam('manifest');
        $item = $event->getParam('item');
        $primaryMedia = $item->primaryMedia();
        if ($primaryMedia) {
            if ($primaryMedia->ingester() == 'remoteFile') {
                $thumbnailURL = $primaryMedia->mediaData()['thumbnail'];
                if ($thumbnailURL) {
                    $manifest['thumbnail'] = [
                        [
                            'id' => $thumbnailURL,
                            'type' => 'Image'
                        ]
                    ];
                    $changed = true;
                }
            }
        }
        $rights = $item->value('dcterms:rights', ['all' => true, 'type' => 'uri']);
        $hasrights = false;
        foreach ($rights as $rightsstatement) {
            if (str_contains($rightsstatement->uri(), "creativecommons.org")) {
                $manifest['rights'] = $rightsstatement->uri();
                $changed = true;
                $hasrights = true;
                break;
            }
        }
        if (!$hasrights) {
            foreach ($rights as $rightsstatement) {
                if (str_contains($rightsstatement->uri(), "rightsstatements.org")) {
                    $manifest['rights'] = $rightsstatement->uri();
                    $changed = true;
                    break;
                }
            }
        }
        if ($changed) {
            $event->setParam('manifest', $manifest);
        }
    }

    public function updateIiif2ThumbnailRights(Event $event)
    {
        $changed = false;
        $manifest = $event->getParam('manifest');
        $item = $event->getParam('item');
        $primaryMedia = $item->primaryMedia();
        if ($primaryMedia) {
            if ($primaryMedia->ingester() == 'remoteFile') {
                $thumbnailURL = $primaryMedia->mediaData()['thumbnail'];
                if ($thumbnailURL) {
                    $manifest['thumbnail'] = [
                        [
                            '@id' => $thumbnailURL,
                            '@type' => 'dctypes:Image'
                        ]
                    ];
                    $changed = true;
                }
            }
        }
        $rights = $item->value('dcterms:rights', ['all' => true, 'type' => 'uri']);
        $hasrights = false;
        foreach ($rights as $rightsstatement) {
            if (str_contains($rightsstatement->uri(), "creativecommons.org")) {
                $manifest['license'] = $rightsstatement->uri();
                $changed = true;
                $hasrights = true;
                break;
            }
        }
        if (!$hasrights) {
            foreach ($rights as $rightsstatement) {
                if (str_contains($rightsstatement->uri(), "rightsstatements.org")) {
                    $manifest['license'] = $rightsstatement->uri();
                    $changed = true;
                    break;
                }
            }
        }
        if ($changed) {
            $event->setParam('manifest', $manifest);
        }
    }

    // public function hideItemsOnSite(Event $event)
    // {
    //     $view = $event->getTarget();
    //     $item = $view->item;
    //     $sites = $item->sites();
    //     $currentSite = $view->currentSite();
    //     if (!$sites || !in_array($currentSite, $sites)) {
    //         $model = new ViewModel;
    //         $model->setTemplate('error/404');
    //         $model->setVariable('message', 'This item is not available on this site.');
    //         $viewRenderer = $this->getServiceLocator()->get('Application')->getServiceManager()->get('ViewRenderer');
    //         $content = $viewRenderer->render($model);
    //         $parentModel = $view->ViewModel()->getCurrent();
    //         $parentModel->setTemplate('layout/layout');
    //         $parentModel->setVariable('content', $content);
    //         $parentModel->setVariable('site', $currentSite);
    //         echo $viewRenderer->render($parentModel);
    //         http_response_code(404); //Added the line of code as per suggested in the comment by B1NARY
    //         exit();
    //     }
    // }
}