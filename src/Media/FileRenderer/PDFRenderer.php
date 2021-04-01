<?php
namespace FITModule\Media\FileRenderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\FileRenderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;

class PDFRenderer implements RendererInterface
{
    public function render(
        PhpRenderer $view,
        MediaRepresentation $media,
        array $options = []
    ) {
        $view->headLink()->appendStylesheet($view->assetUrl('css/pdf.css', 'FITModule'));
        $view->headScript()->appendFile('//cdnjs.cloudflare.com/ajax/libs/pdfobject/2.1.1/pdfobject.min.js');
        $pdfURL = $media->originalUrl();
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
              pagemode: "none"
            },
            forcePDFJS: true,
            PDFJS_URL: "/modules/FITModule/asset/js/pdfjs/web/viewer.html"
          };

          var myPDF = PDFObject.embed("' . $pdfURL . '", "#pdf-' . $media->id() . '", options);
          </script>';
        return $pdfViewer;
    }
}
