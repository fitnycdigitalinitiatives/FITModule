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
    }
}
