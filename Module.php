<?php
/**
 * FITModule
 */
namespace FITModule;

use Omeka\Module\AbstractModule;
use Zend\View\Renderer\PhpRenderer;
use Zend\Mvc\Controller\AbstractController;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\ModuleManager\ModuleManager;
use FITModule\Form\ConfigForm;

class Module extends AbstractModule
{
    /** Module body **/

    /** Load AWS SDK **/
    public function init(ModuleManager $moduleManager)
    {
        require_once __DIR__ . '/vendor/aws/aws-autoloader.php';
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
            function (Event $event) {
                if (($event->getTarget()->media->ingester() == 'remoteImage') || ($event->getTarget()->media->ingester() == 'remoteVideo')) {
                    $view = $event->getTarget();
                    $assetUrl = $view->plugin('assetUrl');
                    $view->headLink()->appendStylesheet($assetUrl('css/FITModuleMoreMediaMeta.css', 'FITModule'));
                    $view->headScript()->appendFile($assetUrl('js/FITModuleS3Presigned.js', 'FITModule'), 'text/javascript', ['defer' => 'defer']);
                    $view->headScript()->appendFile('https://cdn.jsdelivr.net/npm/clipboard@2.0.6/dist/clipboard.min.js', 'text/javascript', ['defer' => 'defer']);
                    echo $event->getTarget()->partial('common/more-media-meta');
                }
            }
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.add.after',
            [$this, 'controlledVocabularCss']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Item',
            'view.edit.after',
            [$this, 'controlledVocabularCss']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.add.after',
            [$this, 'controlledVocabularCss']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\ItemSet',
            'view.edit.after',
            [$this, 'controlledVocabularCss']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.add.after',
            [$this, 'controlledVocabularCss']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.edit.after',
            [$this, 'controlledVocabularCss']
        );
    }

    public function controlledVocabularCss(Event $event)
    {
        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()->appendStylesheet($assetUrl('css/FITModuleControlledVocabulary.css', 'FITModule'));
    }
}
