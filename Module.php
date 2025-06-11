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
use Laminas\EventManager\EventInterface;
use Laminas\ModuleManager\ModuleEvent;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\MvcEvent;
use Laminas\Session\Container;
use FITModule\Form\ConfigForm;
use FITModule\Job\IndexOcr as Indexer;
use Aws\DynamoDb\DynamoDbClient;
use Aws\DynamoDb\Marshaler;
use Omeka\Entity\Item;
use Omeka\Entity\Media;

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
        $acl->allow(null, [
            'FITModule\Controller\Redirect',
            'FITModule\Controller\Site\SiteLogin',
            'FITModule\Controller\IiifSearch\v1\IiifSearch',
            // 'FITModule\Controller\IiifSearch\v2\IiifSearch'
        ]);
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
            'solr_hostname' => $settings->get('fit_module_solr_hostname'),
            'solr_port' => $settings->get('fit_module_solr_port'),
            'solr_path' => $settings->get('fit_module_solr_path'),
            'solr_connection' => $settings->get('fit_module_solr_connection'),
            'solr_login' => $settings->get('fit_module_solr_login'),
            'solr_password' => $settings->get('fit_module_solr_password'),
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
        $settings->set('fit_module_solr_hostname', $formData['solr_hostname']);
        $settings->set('fit_module_solr_port', $formData['solr_port']);
        $settings->set('fit_module_solr_path', $formData['solr_path']);
        $settings->set('fit_module_solr_connection', $formData['solr_connection']);
        $settings->set('fit_module_solr_login', $formData['solr_login']);
        $settings->set('fit_module_solr_password', $formData['solr_password']);
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
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.create.post',
            [$this, 'indexOCR']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\ItemAdapter',
            'api.update.post',
            [$this, 'indexOCR']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.create.post',
            [$this, 'indexOCR']
        );
        $sharedEventManager->attach(
            'Omeka\Api\Adapter\MediaAdapter',
            'api.update.post',
            [$this, 'indexOCR']
        );
        // Attach to restricted site setting to the site settings form
        $sharedEventManager->attach(
            'Omeka\Form\SiteSettingsForm',
            'form.add_elements',
            [
                $this,
                'addSiteLoginSetting',
            ]
        );

        // Attach to the router event to redirect restricted sites to sitelogin page
        $sharedEventManager->attach('*', MvcEvent::EVENT_ROUTE, [
            $this,
            'redirectToSiteLogin',
        ]);
    }

    public function displayRemoteMetadataSidebar(Event $event)
    {
        if ($event->getTarget()->media->ingester() == 'remoteFile') {
            $view = $event->getTarget();
            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()->appendStylesheet($assetUrl('css/FITModuleMoreMediaMeta.css', 'FITModule'));
            $view->headScript()->appendFile($assetUrl('js/FITModuleS3Presigned.js', 'FITModule'), 'text/javascript', ['defer' => 'defer']);
            $view->headScript()->appendFile('https://cdn.jsdelivr.net/npm/clipboard@2.0.6/dist/clipboard.min.js', 'text/javascript', ['defer' => 'defer']);
            echo $event->getTarget()->partial('common/more-remote-file-media-meta');
        } elseif ($event->getTarget()->media->ingester() == 'remoteCompoundObject') {
            $view = $event->getTarget();
            $assetUrl = $view->plugin('assetUrl');
            $view->headLink()->appendStylesheet($assetUrl('css/FITModuleMoreMediaMeta.css', 'FITModule'));
            $view->headScript()->appendFile($assetUrl('js/FITModuleS3Presigned.js', 'FITModule'), 'text/javascript', ['defer' => 'defer']);
            $view->headScript()->appendFile('https://cdn.jsdelivr.net/npm/clipboard@2.0.6/dist/clipboard.min.js', 'text/javascript', ['defer' => 'defer']);
            echo $event->getTarget()->partial('common/more-remote-compound-media-meta');
        }
    }
    public function attachRemoteMetadataJson(Event $event)
    {
        $resource = $event->getTarget();
        $primaryMedia = $resource->primaryMedia();
        if ($primaryMedia) {
            $thumbnailURL = "";
            if ($primaryMedia->ingester() == 'remoteFile') {
                $thumbnailURL = $primaryMedia->mediaData()['thumbnail'];
            } elseif ($primaryMedia->ingester() == 'remoteCompoundObject') {
                foreach ($primaryMedia->mediaData()['components'] as  $component) {
                    if ($component['thumbnail']) {
                        $thumbnailURL = $component['thumbnail'];
                        break;
                    }
                }
            }
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
        } elseif (($primaryMedia = $item->primaryMedia()) && ($primaryMedia->ingester() == 'remoteCompoundObject')) {
            foreach ($primaryMedia->mediaData()['components'] as $component) {
                if ($thumbnailURL = $component['thumbnail']) {
                    $view->headMeta()->setProperty('og:image', $thumbnailURL);
                    $view->headMeta()->setProperty('twitter:image', $thumbnailURL);
                    break;
                }
            }
        } elseif (($primaryMedia = $item->primaryMedia()) && ($thumbnailURL = $primaryMedia->thumbnailUrl('medium'))) {
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
            $batch = [];
            $itemPublic = $item->isPublic();
            foreach ($mediaSet as $media) {
                $mediaPublic = $media->isPublic();
                if (($media->ingester() == 'remoteFile') && ($accessURL = $media->mediaData()['access'])) {
                    $parsed_url = parse_url($accessURL);
                    $key = ltrim($parsed_url["path"], '/');
                    $extension = pathinfo($key, PATHINFO_EXTENSION);
                    if ($extension == 'tif') {
                        $visibility = (($itemPublic) && ($mediaPublic)) ? 'public' : 'private';
                        $batch[] = ['key' => $key, 'visibility' => $visibility];
                    }
                } elseif (($media->ingester() == 'remoteCompoundObject') && ($components = $media->mediaData()['components'])) {
                    foreach ($components as $component) {
                        if ($component['access']) {
                            $parsed_url = parse_url($component['access']);
                            $key = ltrim($parsed_url["path"], '/');
                            $extension = pathinfo($key, PATHINFO_EXTENSION);
                            if ($extension == 'tif') {
                                $visibility = (($itemPublic) && ($mediaPublic)) ? 'public' : 'private';
                                $batch[] = ['key' => $key, 'visibility' => $visibility];
                            }
                        }
                    }
                }
            }
            $marshal = new Marshaler();
            foreach (array_chunk($batch, 25) as $Items) {
                foreach ($Items as $Item) {
                    $BatchWrite['RequestItems'][$table][] = ['PutRequest' => ['Item' => $marshal->marshalItem($Item)]];
                }
                try {
                    $response = $client->batchWriteItem($BatchWrite);
                    if ($response['@metadata']['statusCode'] != 200) {
                        throw new \Exception("Unable to write visibility to DynamoDB. Please contact an administrator. Status code: " . $response['@metadata']['statusCode'], 1);
                    }
                    if ($response["UnprocessedItems"]) {
                        throw new \Exception("Unable to write visibility to DynamoDB. Unprocessed Items: " . json_encode($response["UnprocessedItems"]), 1);
                    }

                    $BatchWrite = [];
                } catch (Exception $e) {
                    throw new \Exception("Unable to write visibility to DynamoDB. Please contact an administrator. " . $e->getMessage(), 1);
                }
            }
        }
    }

    /**
     * Index OCR attached to remote compound objects.
     *
     * @param Event $event
     */
    public function indexOCR(Event $event)
    {
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
        if ($settings->get('fit_module_solr_connection')) {
            $response = $event->getParam('response');
            $entity = $response->getContent();
            $mediaEntityList = [];
            $mediaIdList = [];
            if ($entity instanceof Item) {
                $mediaEntityList = $entity->getMedia();
            } elseif ($entity instanceof Media) {
                $mediaEntityList = [$entity];
            }

            foreach ($mediaEntityList as $mediaEntity) {
                if (($mediaEntity->getIngester() == 'remoteCompoundObject') && ($data = $mediaEntity->getData()) && (array_key_exists('indexed', $data)) && (!$data['indexed']) && $data['components']) {
                    foreach ($data['components'] as $component) {
                        if ($component['ocr']) {
                            $mediaIdList[] = $mediaEntity->getId();
                            break;
                        }
                    }
                }
            }
            if ($mediaIdList) {
                $jobArgs = [
                    'mediaIdList' => $mediaIdList,
                ];
                $jobDispatcher = $this->getServiceLocator()->get(\Omeka\Job\Dispatcher::class);
                $jobDispatcher->dispatch(Indexer::class, $jobArgs);
            }
        }
    }

    /**
     * Adds a Checkbox element to the site settings form
     * This element is automatically handled by Omeka in the site_settings table
     *
     * @param EventInterface $event
     */
    public function addSiteLoginSetting(EventInterface $event)
    {
        /** @var \Omeka\Form\UserForm $form */
        $form = $event->getTarget();

        $siteSettings = $form->getSiteSettings();
        $options = $form->getOptions();
        $options['element_groups']['fit_module_sitelogin'] = 'Site Login';
        $form->setOption('element_groups', $options['element_groups']);

        $form->add(
            [
                'name' => 'fit_module_loginpage',
                'type' => 'Checkbox',
                'options' => [
                    'element_group' => 'fit_module_sitelogin',
                    'label' => 'Add login page to this site',
                    'info' => 'Adds a login page to the site at /login.',
                ],
                'attributes' => [
                    'value' => (bool) $siteSettings->get(
                        'fit_module_sitelogin',
                        false
                    ),
                ],
            ]
        );
        $form->add(
            [
                'name' => 'fit_module_restrictedsites',
                'type' => 'Checkbox',
                'options' => [
                    'element_group' => 'fit_module_sitelogin',
                    'label' => 'Restrict access to this site to authenticated users',
                    'info' => 'Requires site to be logged in through SSO or other autheticated user. Site visibility must be set to Visible (in Site info panel) for this feature to work properly.',
                ],
                'attributes' => [
                    'value' => (bool) $siteSettings->get(
                        'fit_module_restrictedsites',
                        false
                    ),
                ],
            ]
        );
        return;
    }

    /**
     * Redirects all site requests to sitelogin route if site is restricted and
     * user is not logged in.
     *
     * @param MvcEvent $event
     * @return Response
     */
    public function redirectToSiteLogin(MvcEvent $event)
    {
        // If the user is already logged in they can continue
        $serviceLocator = $event->getApplication()->getServiceManager();
        $auth = $serviceLocator->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            // User is logged in.
            return;
        }

        // Check to see if this is a site and if it is restricted
        $routeMatch = $event->getRouteMatch();

        if ($routeMatch->getParam('__SITE__')) {
            $siteSlug = $event->getRouteMatch()->getParam('site-slug');
            // Allow OAI for Designer Files
            if ($siteSlug == 'designerfiles' && $routeMatch->getMatchedRouteName() == 'site/oai-pmh') {
                return;
            }
            $site = $serviceLocator->get('Omeka\ApiManager')->read('sites', ['slug' => $siteSlug])->getContent();
            $siteSettings = $serviceLocator->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($site->id());
            $restricted = $siteSettings->get('fit_module_restrictedsites', null);
            if ($restricted && ($routeMatch->getMatchedRouteName() != 'site/site-login')) {
                // Redirect to login page
                $url = $event->getRouter()->assemble(
                    [
                        'site-slug' => $siteSlug,
                    ],
                    [
                        'name' => 'site/site-login',
                    ]
                );
                $session = Container::getDefaultManager()->getStorage();
                $session->offsetSet('redirect_url', $event->getRequest()->getUriString());
                $response = $event->getResponse();
                $response->getHeaders()->addHeaderLine('Location', $url);
                $response->setStatusCode(302); // redirect
                $response->sendHeaders();
                return $response;
            }
        }
    }
}
