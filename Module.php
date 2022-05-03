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
use FITModule\Form\ConfigForm;

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
    public function contributorRelators($event)
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

    public function removeContributorRelators($event)
    {
        // Check if this is a site request
        $routeMatch = $this->getServiceLocator()->get('Application')->getMvcEvent()->getRouteMatch();
        if ($routeMatch->getParam('__SITE__')) {
            $values = $event->getParam('values');
            unset($values["bf:role"]);
            $event->setParam('values', $values);
        }
    }
}
