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
use Laminas\ModuleManager\ModuleManager;
use FITModule\Form\ConfigForm;

class Module extends AbstractModule
{
    /** Module body **/

    /** Load AWS SDK **/
    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/autoload.php';
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
            's3_region' => $settings->get('fit_module_s3_region'),
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
        $settings->set('fit_module_s3_region', $formData['s3_region']);
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
            if ($primaryMedia->ingester() == 'remoteImage' || $primaryMedia->ingester() == 'remoteVideo' || $primaryMedia->ingester() == 'remoteFile') {
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
}
