<?php
namespace FITModule\View\Helper;

use Omeka\Api\Representation\AbstractRepresentation;
use Laminas\View\Helper\AbstractHtmlElement;
use Laminas\ServiceManager\ServiceLocatorInterface;

/**
 * View helper for rendering a thumbnail image.
 */
class FITModuleThumbnail extends AbstractHtmlElement
{
    /**
     * Render a thumbnail image.
     *
     * @param AbstractRepresentation $representation
     * @param string $type
     * @param array $attribs
     */
    public function __invoke(AbstractRepresentation $representation, $type, array $attribs = [])
    {
        $triggerHelper = $this->getView()->plugin('trigger');
        $thumbnail = $representation->thumbnail();
        $primaryMedia = $representation->primaryMedia();
        if (!$thumbnail && !$primaryMedia) {
            $services = $representation->getServiceLocator();
            $thumbnailManager = $services->get('Omeka\File\ThumbnailManager');
            $fallbacks = $thumbnailManager->getFallbacks();
            $resourceClass = $representation->displayResourceClassLabel();
            if ($resourceClass && isset($fallbacks[$resourceClass])) {
                // Then fall back on a match against the top-level type, e.g. "image"
                $fallback = $fallbacks[$resourceClass];
            } else {
                $fallback = $thumbnailManager->getDefaultFallback();
            }
            $assetUrl = $this->getView()->plugin('assetUrl');
            $attribs['src'] = $assetUrl($fallback[0], $fallback[1], true);
            // Trigger attribs event
            $params = compact('attribs', 'thumbnail', 'primaryMedia', 'representation', 'type');
            $params = $triggerHelper('view_helper.thumbnail.attribs', $params, true);
            $attribs = $params['attribs'];

            if (!isset($attribs['alt'])) {
                $attribs['alt'] = '';
            }

            return sprintf('<img%s>', $this->htmlAttribs($attribs));
        }

        $thumbnailURL = '';

        if (($primaryMedia) && ($primaryMedia->ingester() == 'remoteFile')) {
            $thumbnailURL = $primaryMedia->mediaData()['thumbnail'];
        }

        if (($thumbnailURL == '') && ($primaryMedia)) {
            $thumbnailURL = $primaryMedia->thumbnailUrl($type);
        }


        $attribs['src'] = $thumbnail ? $thumbnail->assetUrl() : $thumbnailURL;

        // Trigger attribs event
        $params = compact('attribs', 'thumbnail', 'primaryMedia', 'representation', 'type');
        $params = $triggerHelper('view_helper.thumbnail.attribs', $params, true);
        $attribs = $params['attribs'];

        if (!isset($attribs['alt'])) {
            $attribs['alt'] = '';
        }

        return sprintf('<img%s>', $this->htmlAttribs($attribs));
    }
}
