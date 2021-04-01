<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteFile implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $hyperlink = $view->plugin('hyperlink');
        $accessURL = $media->mediaData()['access'];
        $thumbnail = $view->thumbnail($media, 'square');
        $mediaType = $media->mediaType();
        if ($accessURL != '') {
            if ($mediaType == "application/pdf") {
                $view->headLink()->appendStylesheet($view->assetUrl('css/pdf.css', 'FITModule'));
                $view->headScript()->appendFile('//cdnjs.cloudflare.com/ajax/libs/pdfobject/2.1.1/pdfobject.min.js');
                $pdfURL = $view->s3presigned($accessURL);
                $pdfViewer =
                '<div id="results" class="hidden"></div>

                  <div id="pdf-' . $media->id() . '"></div>

                  <script>
                  var options = {
                  	pdfOpenParams: {
                  		navpanes: 0,
                  		toolbar: 0,
                  		statusbar: 0,
                  		view: "FitV",
                  		pagemode: "thumbs",
                  		page: 2
                  	},
                  	forcePDFJS: true,
                  	PDFJS_URL: "/modules/FITModule/asset/js/pdfjs/web/viewer.html"
                  };

                  var myPDF = PDFObject.embed("' . $pdfURL . '", "#pdf-' . $media->id() . '", options);
                  </script>';
                return $pdfViewer;
            } elseif (strpos($mediaType, 'audio') === 0) {
                return sprintf(
                    '<audio src="%s" controls>%s</audio>',
                    $accessURL,
                    $view->hyperlink('Audio file', $view->s3presigned($accessURL))
                );
            } else {
                return $hyperlink->raw($thumbnail, $view->s3presigned($accessURL));
            }
        }
    }
}
