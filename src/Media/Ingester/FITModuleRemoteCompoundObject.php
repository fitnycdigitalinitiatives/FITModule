<?php

namespace FITModule\Media\Ingester;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Ingester\MutableIngesterInterface;
use Omeka\Api\Request;
use Omeka\Entity\Media;
use Laminas\Form\Element\Text;
use Laminas\Form\Element\Checkbox;
use Laminas\Form\Element\Number;
use Laminas\Form\Element\Url as UrlElement;
use Omeka\Stdlib\ErrorStore;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteCompoundObject implements MutableIngesterInterface
{
    protected $component_parts = [
        ['name' => 'dcterms:title', 'label' => 'Component Title', 'type' => 'text'],
        ['name' => 'access', 'label' => 'Access URL', 'type' => 'url'],
        ['name' => 'thumbnail', 'label' => 'Thumbnail URL', 'type' => 'url'],
        ['name' => 'ocr', 'label' => 'OCR URL', 'type' => 'url'],
        ['name' => 'dcterms:identifier', 'label' => 'File name', 'type' => 'text'],
        ['name' => 'exif:width', 'label' => 'Image width', 'type' => 'text'],
        ['name' => 'exif:height', 'label' => 'Image height', 'type' => 'text'],
    ];
    public function updateForm(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $archival = array_key_exists('archival', $media->mediaData()) ? $media->mediaData()['archival'] : '';
        $replica = array_key_exists('replica', $media->mediaData()) ? $media->mediaData()['replica'] : '';
        $components = array_key_exists('components', $media->mediaData()) ? $media->mediaData()['components'] : [];
        $mets = array_key_exists('mets', $media->mediaData()) ? $media->mediaData()['mets'] : '';
        $pdf = array_key_exists('pdf', $media->mediaData()) ? $media->mediaData()['pdf'] : '';
        $pdfThumbnail = array_key_exists('pdfThumbnail', $media->mediaData()) ? $media->mediaData()['pdfThumbnail'] : '';
        $indexed = array_key_exists('indexed', $media->mediaData()) ? $media->mediaData()['indexed'] : '';
        $index_offset = array_key_exists('index_offset', $media->mediaData()) ? $media->mediaData()['index_offset'] : 0;
        return $this->getForm($view, $archival, $replica, $components, $mets, $pdf, $pdfThumbnail, $indexed, $index_offset);
    }

    public function form(PhpRenderer $view, array $options = [])
    {
        return $this->getForm($view);
    }

    public function getLabel()
    {
        return 'Remote Compound Object (Book)'; // @translate
    }

    public function getRenderer()
    {
        return 'remoteCompoundObject';
    }

    public function ingest(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $archival = isset($data['archival']) ? $data['archival'] : '';
        $replica = isset($data['replica']) ? $data['replica'] : '';
        $components = [];
        if ($data['components']) {
            foreach ($data['components'] as $component) {
                $thisComponentData = [];
                foreach ($this->component_parts as $component_part) {
                    $thisComponentData[$component_part['name']] = isset($component[$component_part['name']]) ? $component[$component_part['name']] : "";
                }
                // Only add the component if it has any value, if all are blank ignore
                if (array_filter($thisComponentData)) {
                    $components[] = $thisComponentData;
                }
            }
        }
        $mets = isset($data['mets']) ? $data['mets'] : '';
        $pdf = isset($data['pdf']) ? $data['pdf'] : '';
        $pdfThumbnail = isset($data['pdfThumbnail']) ? $data['pdfThumbnail'] : '';
        $indexed = isset($data['indexed']) ? $data['indexed'] : '';
        $index_offset = isset($data['index_offset']) ? $data['index_offset'] : 0;
        $mediaData = ['archival' => $archival, 'replica' => $replica, 'components' => $components, 'mets' => $mets, 'pdf' => $pdf, 'pdfThumbnail' => $pdfThumbnail, 'indexed' => $indexed, 'index_offset' => $index_offset];
        $media->setData($mediaData);
        // attempt to get MIME for Media Type
        $ext = '';
        if (isset($data['dcterms:identifier'])) {
            //check for service file first
            foreach ($data['dcterms:identifier'] as $key => $value) {
                if (isset($value['o:label']) && ($value['o:label'] == 'service-file')) {
                    $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                    break;
                }
            }
            //check for original file
            if ($ext == '') {
                foreach ($data['dcterms:identifier'] as $key => $value) {
                    if (isset($value['o:label']) && ($value['o:label'] == 'original-file')) {
                        $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                        break;
                    }
                }
            }
        }
        //check first component piece
        if (isset($components[0]["access"]) && ($ext == '')) {
            $ext = pathinfo($components[0]["access"], PATHINFO_EXTENSION);
        }
        if ($ext != '') {
            $builder = \Mimey\MimeMappingBuilder::create();
            $builder->add('image/jp2', 'jp2');
            $builder->add('image/heif', 'heic');
            $builder->add('text/vtt', 'vtt');
            $builder->add('application/warc', 'warc');
            $builder->add('application/warc', 'warc.gz');
            $mimes = new \Mimey\MimeTypes($builder->getMapping());
            $media->setMediaType($mimes->getMimeType($ext));
        } else {
            $media->setMediaType(null);
        }
    }

    public function update(Media $media, Request $request, ErrorStore $errorStore)
    {
        $data = $request->getContent();
        $thisMediaData = [];
        if (isset($data['o:media']['__index__'])) {
            $thisMediaData = $data['o:media']['__index__'];
        } elseif (isset($data['data'])) {
            $thisMediaData = $data['data'];
        }
        // check to see update is to media itself of media metadata, ie batch edit, only necessary to check for presence of one remote file component
        if (isset($thisMediaData['archival'])) {
            $archival = isset($thisMediaData['archival']) ? $thisMediaData['archival'] : '';
            $replica = isset($thisMediaData['replica']) ? $thisMediaData['replica'] : '';
            $components = [];
            if ($thisMediaData['components']) {
                foreach ($thisMediaData['components'] as $component) {
                    $thisComponentData = [];
                    foreach ($this->component_parts as $component_part) {
                        $thisComponentData[$component_part['name']] = isset($component[$component_part['name']]) ? $component[$component_part['name']] : "";
                    }
                    // Only add the component if it has value
                    if (array_filter($thisComponentData)) {
                        $components[] = $thisComponentData;
                    }
                }
            }
            $mets = isset($thisMediaData['mets']) ? $thisMediaData['mets'] : '';
            $pdf = isset($thisMediaData['pdf']) ? $thisMediaData['pdf'] : '';
            $pdfThumbnail = isset($thisMediaData['pdfThumbnail']) ? $thisMediaData['pdfThumbnail'] : '';
            $indexed = isset($thisMediaData['indexed']) ? $thisMediaData['indexed'] : '';
            $index_offset = isset($thisMediaData['index_offset']) ? $thisMediaData['index_offset'] : 0;
            $mediaData = ['archival' => $archival, 'replica' => $replica, 'components' => $components, 'mets' => $mets, 'pdf' => $pdf, 'pdfThumbnail' => $pdfThumbnail, 'indexed' => $indexed, 'index_offset' => $index_offset];
            $media->setData($mediaData);
            // attempt to get MIME for Media Type
            $ext = '';
            if (isset($data['dcterms:identifier'])) {
                //check for service file first
                foreach ($data['dcterms:identifier'] as $key => $value) {
                    if (isset($value['o:label']) && ($value['o:label'] == 'service-file')) {
                        $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                        break;
                    }
                }
                //check for original file
                if ($ext == '') {
                    foreach ($data['dcterms:identifier'] as $key => $value) {
                        if (isset($value['o:label']) && ($value['o:label'] == 'original-file')) {
                            $ext = pathinfo($value['@id'], PATHINFO_EXTENSION);
                            break;
                        }
                    }
                }
            }
            //check first component piece
            if (isset($components[0]["access"]) && ($ext == '')) {
                $ext = pathinfo($components[0]["access"], PATHINFO_EXTENSION);
            }
            if ($ext != '') {
                $builder = \Mimey\MimeMappingBuilder::create();
                $builder->add('image/jp2', 'jp2');
                $builder->add('image/heif', 'heic');
                $builder->add('text/vtt', 'vtt');
                $builder->add('application/warc', 'warc');
                $builder->add('application/warc', 'warc.gz');
                $mimes = new \Mimey\MimeTypes($builder->getMapping());
                $media->setMediaType($mimes->getMimeType($ext));
            } else {
                $media->setMediaType(null);
            }
        }
    }

    protected function getForm(PhpRenderer $view, $archival = '', $replica = '', $components = [], $mets = '', $pdf = '', $pdfThumbnail = '', $indexed = '', $index_offset = 0)
    {
        $view->headScript()->appendFile($view->assetUrl('js/component-media.js', 'FITModule'), 'text/javascript');
        $view->headLink()->appendStylesheet($view->assetUrl('css/component-media.css', 'FITModule'));
        $archivalInput = new UrlElement('o:media[__index__][archival]');
        $archivalInput->setOptions([
            'label' => 'Archival package URL', // @translate
        ]);
        $archivalInput->setAttributes([
            'value' => $archival,
        ]);

        $replicaInput = new UrlElement('o:media[__index__][replica]');
        $replicaInput->setOptions([
            'label' => 'Replica package URL', // @translate
        ]);
        $replicaInput->setAttributes([
            'value' => $replica,
        ]);

        $metsInput = new UrlElement('o:media[__index__][mets]');
        $metsInput->setOptions([
            'label' => 'METS file URL', // @translate
        ]);
        $metsInput->setAttributes([
            'value' => $mets,
        ]);

        $pdfInput = new UrlElement('o:media[__index__][pdf]');
        $pdfInput->setOptions([
            'label' => 'PDF version URL', // @translate
        ]);
        $pdfInput->setAttributes([
            'value' => $pdf,
        ]);
        $pdfThumbnailInput = new UrlElement('o:media[__index__][pdfThumbnail]');
        $pdfThumbnailInput->setOptions([
            'label' => 'PDF version thumbnail URL', // @translate
        ]);
        $pdfThumbnailInput->setAttributes([
            'value' => $pdfThumbnail,
        ]);
        $indexedInput = new Checkbox('o:media[__index__][indexed]');
        $indexedInput->setOptions([
            'label' => 'Has this been indexed in the full-text index?', // @translate
        ]);
        $indexedInput->setCheckedValue('1');
        $indexedInput->setUncheckedValue('');
        $indexedInput->setAttributes([
            'value' => $indexed,
        ]);

        if (!$index_offset) {
            $index_offset = 0;
        }
        $index_offsetInput = new Number('o:media[__index__][index_offset]');
        $index_offsetInput->setOptions([
            'label' => 'Index offset.',
            'info' => 'The file/page numbering should start at 1, ie 001, but some materials were digitized with the first page as 000, so in those cases an offset of 1 needs to be set for full-text search to work correctly. Leave set as 0 unless an offset is needed.',
        ]);
        $index_offsetInput->setAttributes([
            'value' => $index_offset,
            'min'  => '0',
            'max'  => '1',
            'step' => '1',
        ]);
        $component_template = '<div class="component" data-key="__componentIndex__"><span class="remote-sortable-handle"></span><div class="input-body">';
        foreach ($this->component_parts as $component_part) {
            $component_template .= '<div class="input">';
            switch ($component_part['type']) {
                case 'url':
                    $thisInput = new UrlElement('o:media[__index__][components][__componentIndex__][' . $component_part['name'] . ']');
                    break;
                default:
                    $thisInput = new Text('o:media[__index__][components][__componentIndex__][' . $component_part['name'] . ']');
                    break;
            }
            $thisInput->setOptions([
                'label' => $component_part['label'],
            ]);
            $thisInput->setAttributes([
                'value' => "",
            ]);
            $component_template .= $view->formLabel($thisInput);;
            $component_template .= $view->formElement($thisInput);;
            $component_template .= '</div>';
        }
        $component_template .= '</div><div class="input-footer"><ul class="actions"><li><a class="o-icon-delete remove-component" title="Remove component" href="#" aria-label="Remove component"></a></li></ul></div></div>';
        $component_rows = "";
        if ($components) {
            foreach ($components as $key => $component) {
                $component_rows .= '<div class="component" data-key="' . $key . '"><span class="remote-sortable-handle"></span><div class="input-body">';
                foreach ($this->component_parts as $component_part) {
                    $component_rows .= '<div class="input">';
                    switch ($component_part['type']) {
                        case 'url':
                            $thisInput = new UrlElement('o:media[__index__][components][' . $key . '][' . $component_part['name'] . ']');
                            break;
                        default:
                            $thisInput = new Text('o:media[__index__][components][' . $key . '][' . $component_part['name'] . ']');
                            break;
                    }
                    $thisInput->setOptions([
                        'label' => $component_part['label'],
                    ]);
                    $thisInput->setAttributes([
                        'value' => isset($component[$component_part['name']]) ? $component[$component_part['name']] : "",
                    ]);
                    $component_rows .= $view->formLabel($thisInput);;
                    $component_rows .= $view->formElement($thisInput);;
                    $component_rows .= '</div>';
                }
                $component_rows .= '</div><div class="input-footer"><ul class="actions"><li><a class="o-icon-delete remove-component" title="Remove component" href="#" aria-label="Remove component"></a></li></ul></div></div>';
            }
        } else {
            $component_rows = str_replace("__componentIndex__", "0", $component_template);
        }
        $components_fieldSet = <<<END
            <div class="remote-components field" data-component-count="1">
            <div class="field-meta">Components</div>
            <div class="inputs">
            {$component_rows}
            <button type="button" class="add-component"><span class="o-icon-add" title="Add component" aria-label="Add component"></span>Component</button>
            </div>
            </div>
            END;
        return $view->formRow($archivalInput) . $view->formRow($replicaInput) . $view->formRow($metsInput) . $view->formRow($pdfInput) . $view->formRow($pdfThumbnailInput) . $view->formRow($indexedInput) . $view->formRow($index_offsetInput) . $components_fieldSet . '<span id="remote-component-template" data-template="' . $view->escapeHtml($component_template) . '"></span>';
    }
}
