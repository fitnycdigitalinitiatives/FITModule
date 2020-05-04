<?php
/**
 * FITModule
 */
namespace FITModule;

use Omeka\Module\AbstractModule;
use Zend\View\Model\ViewModel;
use Zend\Mvc\Controller\AbstractController;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    /** Module body **/

    /**
     * Get this module's configuration array.
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Media',
            'view.show.sidebar',
            function (Event $event) {
                if ($event->getTarget()->media->ingester() == 'image') {
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
