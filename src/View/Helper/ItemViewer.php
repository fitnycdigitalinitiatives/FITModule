<?php

namespace FITModule\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ItemRepresentation;

class ItemViewer extends AbstractHelper
{
    public function __invoke(ItemRepresentation $item)
    {
        $view = $this->getView();
        $itemMedia = $item->media();
        // Group media by type
        $mediaTypes = [];
        $iiifEndpoint = $view->setting('fit_module_aws_iiif_endpoint');
        foreach ($itemMedia as $key => $media) {
            if ($media->ingester() == 'remoteFile') {
                $accessURL = $media->mediaData()['access'];
                $mediaType = $media->mediaType();
                // image
                if ((strpos($mediaType, 'image') === 0) && ($accessURL != '') && ($iiifEndpoint != '')) {
                    $mediaTypes['iiifImages'][] = $media;
                }
                // video and audio
                elseif ((strpos($mediaType, 'video') === 0) || (strpos($media->mediaType(), 'audio') === 0) || (strpos($media->mediaType(), 'application/mxf') === 0)) {
                    $mediaTypes['videoAudio'][] = $media;
                }
                // pdf
                elseif (($mediaType == "application/pdf") && ($accessURL != '')) {
                    // Ignore release forms
                    if (strtolower($media->displayTitle()) != "release form") {
                        $mediaTypes['pdf'][] = $media;
                    }
                } else {
                    // Ignore captions and ocr files
                    if (!(($mediaType == "text/vtt") || ($mediaType == "application/xml"))) {
                        $mediaTypes['other'][] = $media;
                    }
                }
            } elseif ($media->ingester() == 'iiif') {
                $mediaTypes['iiifImages'][] = $media;
            } elseif ($media->ingester() == 'youtube') {
                $mediaTypes['videoAudio'][] = $media;
            } else {
                $mediaTypes['other'][] = $media;
            }
        }

        $content = "";
        $tabs = "";

        if ((count($mediaTypes) > 1) || (isset($mediaTypes['videoAudio']) && (count($mediaTypes['videoAudio']) > 1)) || (isset($mediaTypes['pdf']) && (count($mediaTypes['pdf']) > 1)) || (isset($mediaTypes['other']) && (count($mediaTypes['other']) > 1))) {
            $view->headLink()->appendStylesheet('https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/css/splide.min.css');
            $view->headScript()->appendFile('https://cdn.jsdelivr.net/npm/@splidejs/splide@4.1.4/dist/js/splide.min.js');
            $view->headLink()->appendStylesheet($view->assetUrl('css/item-viewer.css', 'FITModule'));
            $view->headScript()->appendFile($view->assetUrl('js/item-viewer.js', 'FITModule'), 'text/javascript');
            $panel_id = 1;
            foreach ($mediaTypes as $mediaType => $medias) {
                if ($mediaType == 'iiifImages') {
                    $mirador = $view->miradorViewer($medias[0]->item(), null, ['window' => [
                        'hideWindowTitle' => true,
                    ]]);
                    $title = "Images/Text";
                    $thumbnail = $view->thumbnail($medias[0], 'medium', ['class' => 'img-fluid', 'alt' => $title]);
                    $active = $panel_id == 1 ? 'show active' : '';
                    $selected = $panel_id == 1 ? 'selected' : '';
                    $ariaSelected = $panel_id == 1 ? 'true' : 'false';
                    $content .= <<<END
                    <div class="tab-pane {$active}" id="media-{$panel_id}" role="tabpanel" aria-labelledby="media-{$panel_id}-tab">
                        {$mirador}
                    </div>
                    END;
                    $tabs .= <<<END
                    <li class="splide__slide">
                        <button class="border-0 bg-transparent {$selected}" id="media-{$panel_id}-tab" data-target="#media-{$panel_id}" type="button" role="tab" aria-controls="media-{$panel_id}" aria-selected="{$ariaSelected}" title="{$title}" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-trigger="hover">
                            {$thumbnail}
                        </button>
                    </li>
                    END;
                    $panel_id++;
                } else {
                    foreach ($medias as $media) {
                        $title = $media->displayTitle();
                        $thumbnail = $view->thumbnail($media, 'medium', ['class' => 'img-fluid', 'alt' => $title]);
                        $active = $panel_id == 1 ? 'show active' : '';
                        $selected = $panel_id == 1 ? 'selected' : '';
                        $ariaSelected = $panel_id == 1 ? 'true' : 'false';
                        $content .= <<<END
                        <div class="tab-pane {$active}" id="media-{$panel_id}" role="tabpanel" aria-labelledby="media-{$panel_id}-tab">
                            {$media->render()}
                        </div>
                        END;
                        $tabs .= <<<END
                        <li class="splide__slide">
                            <button class="border-0 bg-transparent {$selected}" id="media-{$panel_id}-tab" data-target="#media-{$panel_id}" type="button" role="tab" aria-controls="media-{$panel_id}" aria-selected="{$ariaSelected}" title="{$title}" data-bs-toggle="tooltip" data-bs-placement="bottom" data-bs-trigger="hover">
                                {$thumbnail}
                            </button>
                        </li>
                        END;
                        $panel_id++;
                    }
                }
            }
        } else {
            foreach ($mediaTypes as $mediaType => $medias) {
                if ($mediaType == 'iiifImages') {
                    $content = $view->miradorViewer($medias[0]->item(), null, ['window' => [
                        'hideWindowTitle' => true,
                    ]]);
                } else {
                    $content = $medias[0]->render();
                }
            }
        }


        return  ['content' => $content, 'tabs' => $tabs];
    }
}
