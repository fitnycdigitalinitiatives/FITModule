<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;

class FITModuleRemoteFile implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $accessURL = $media->mediaData()['access'];
        $mediaType = $media->mediaType();

        // image
        if ((strpos($media->mediaType(), 'image') === 0) && ($accessURL != '')) {
            // code...
        }
        // video
        elseif (strpos($media->mediaType(), 'video') === 0) {
            return $this->remote_video($view, $media);
        }
        // audio
        elseif ((strpos($media->mediaType(), 'audio') === 0) && ($accessURL != '')) {
            // code...
        }
        // pdf
        elseif (($mediaType == "application/pdf") && ($accessURL != '')) {
            // code...
        } else {
            return $this->remote_fallback($view, $media, $accessURL);
        }
    }

    public function remote_image(PhpRenderer $view, MediaRepresentation $media, $accessURL = '')
    {
        return "I am an image";
    }

    public function remote_video(PhpRenderer $view, MediaRepresentation $media)
    {
        $view->headLink()->appendStylesheet($view->assetUrl('css/video.css', 'FITModule'));
        $youtubeID = $media->mediaData()['YouTubeID'];
        $googledriveID = $media->mediaData()['GoogleDriveID'];
        $accessURL = $media->mediaData()['access'];
        if ($youtubeID != '') {
            $url = sprintf('https://www.youtube.com/embed/%s', $youtubeID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-16by9">
                <iframe class="embed-responsive-item" src="%s" allowfullscreen></iframe>
              </div>',
                $url
            );
            return $embed;
        } elseif ($googledriveID != '') {
            $url = sprintf('https://drive.google.com/file/d/%s/preview', $googledriveID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-16by9">
                <iframe class="embed-responsive-item" src="%s" allowfullscreen></iframe>
              </div>',
                $url
            );
            return $embed;
        } elseif ($accessURL != '') {
            $videoURL = $view->s3presigned($accessURL);
            $captionHTML = '';
            // find caption track separately attached to item if the file name matches current file name with vtt extension
            // necessary for caption track to have the same file name as video file, ie video.mp4 and video.vtt
            $fileName = '';
            $values = $media->value('dcterms:identifier', ['all' => true, 'type' => 'uri']);
            foreach ($values as $value) {
                if ($value->value() == "original-file") {
                    $fileName = pathinfo($value->uri(), PATHINFO_FILENAME);
                }
            }
            $item = $media->item();
            $allMedia = $item->media();
            foreach ($allMedia as $relatedMedia) {
                if ($relatedMedia->mediaType() == "text/vtt") {
                    $relatedAccessURL = $relatedMedia->mediaData()['access'];
                    if ($relatedAccessURL != '') {
                        $relatedValues = $relatedMedia->value('dcterms:identifier', ['all' => true, 'type' => 'uri']);
                        foreach ($relatedValues as $relatedValue) {
                            if ($relatedValue->value() == "original-file") {
                                $relatedFileName = pathinfo($relatedValue->uri(), PATHINFO_FILENAME);
                                if ($relatedFileName == $fileName) {
                                    $captionURL = $view->s3presigned($relatedAccessURL);
                                    $captionHTML = sprintf(
                                        '<track src="%s" kind="captions" label="Captions">',
                                        $captionURL
                                    );
                                }
                            }
                        }
                    }
                }
            }
            $video = sprintf(
                '<div class="embed-responsive embed-responsive-16by9">
                <video class="embed-responsive-item" controls crossorigin="anonymous">
                  <source src="%s" type="video/mp4">
                  %s
                </video>
              </div>',
                $videoURL,
                $captionHTML
            );
            return $video;
        } else {
            return $this->remote_fallback($view, $media, $accessURL);
        }
    }

    public function remote_audio(PhpRenderer $view, MediaRepresentation $media, $accessURL = '')
    {
        return sprintf(
            '<audio src="%s" controls>%s</audio>',
            $accessURL,
            $view->hyperlink('Audio file', $view->s3presigned($accessURL))
        );
    }

    public function remote_pdf(PhpRenderer $view, MediaRepresentation $media, $accessURL = '')
    {
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
    }

    public function remote_fallback(PhpRenderer $view, MediaRepresentation $media, $accessURL = '')
    {
        $hyperlink = $view->plugin('hyperlink');
        $thumbnail = $view->thumbnail($media, 'medium');
        $googledriveID = $media->mediaData()['GoogleDriveID'];
        if ($accessURL != '') {
            return $hyperlink->raw($thumbnail, $view->s3presigned($accessURL));
        } elseif ($googledriveID != '') {
            $driveURL = 'https://drive.google.com/file/d/' . $googledriveID . '/view?usp=sharing';
            return $hyperlink->raw($thumbnail, $view->s3presigned($accessURL));
        } else {
            return $thumbnail;
        }
    }
}
