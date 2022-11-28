<?php
namespace FITModule\Media\Renderer;

use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Media\Renderer\RendererInterface;
use Laminas\View\Renderer\PhpRenderer;
use Firebase\JWT\JWT;

class FITModuleRemoteFile implements RendererInterface
{
    public function render(PhpRenderer $view, MediaRepresentation $media, array $options = [])
    {
        $accessURL = $media->mediaData()['access'];
        $mediaType = $media->mediaType();
        $iiifEndpoint = $view->setting('fit_module_aws_iiif_endpoint');

        // image
        if ((strpos($media->mediaType(), 'image') === 0) && ($accessURL != '') && ($iiifEndpoint != '')) {
            return $this->remote_image($view, $media, $accessURL, $iiifEndpoint);
        }
        // video and audio
        elseif ((strpos($media->mediaType(), 'video') === 0) || (strpos($media->mediaType(), 'audio') === 0)) {
            return $this->remote_video_audio($view, $media);
        }
        // pdf
        elseif (($mediaType == "application/pdf") && ($accessURL != '')) {
            return $this->remote_pdf($view, $media, $accessURL);
        } else {
            return $this->remote_fallback($view, $media, $accessURL);
        }
    }

    public function remote_image(PhpRenderer $view, MediaRepresentation $media, $accessURL = '', $iiifEndpoint = '')
    {
        $parsed_url = parse_url($accessURL);
        $key = ltrim($parsed_url["path"], '/');
        $extension = pathinfo($key, PATHINFO_EXTENSION);
        $authorization = "";
        $name = "Anonymous";
        if ($view->identity()) {
            $name = $view->identity()->getName();
        }
        // Create an authorization token if either the media or item is private. Authorized user will only be accessing in the first place if they have permission
        if (!($media->isPublic()) || !($media->item()->isPublic())) {
            $secret_key = $view->setting('fit_module_iiif_secret_key');
            $now_seconds = time();
            $payload = array(
              "iss" => "https://digitalrepository.fitnyc.edu",
              "iat" => $now_seconds,
              "exp" => $now_seconds+(60*60),  // Maximum expiration time is one hour
              "user" => $name,
              "visibility" => "private"
            );
            $authorization = JWT::encode($payload, $secret_key, "HS256");
        }
        // If the item is public, include that in the JWT so the authenticator does not need to look it up in the database.
        // else {
        //     $secret_key = $view->setting('fit_module_iiif_secret_key');
        //     $now_seconds = time();
        //     $payload = array(
        //     "iss" => "https://digitalrepository.fitnyc.edu",
        //     "iat" => $now_seconds,
        //     "exp" => $now_seconds+(60*60),  // Maximum expiration time is one hour
        //     "user" => $name,
        //     "visibility" => "public"
        //   );
        //     $authorization = JWT::encode($payload, $secret_key, "HS256");
        // }
        if ($extension == 'tif') {
            $iiifInfoJson = $iiifEndpoint . str_replace("/", "%2F", rtrim($key, "." . $extension)) . "/info.json";
            $view->headLink()->appendStylesheet($view->assetUrl('css/openseadragon.css', 'FITModule'));
            $view->headScript()->appendFile('https://cdn.jsdelivr.net/npm/openseadragon@2.4/build/openseadragon/openseadragon.min.js', 'text/javascript');
            $view->headScript()->appendFile($view->assetUrl('js/seadragon-view.js', 'FITModule'), 'text/javascript');
            $noscript = $view->translate('OpenSeadragon is not available unless JavaScript is enabled.');
            $image =
            '<div class="openseadragon-frame">
              <div class="loader"></div>
              <div class="openseadragon" id="iiif-' . $media->id() . '" data-infojson="' . $iiifInfoJson . '" data-authtoken="' . $authorization . '"></div>
            </div>
            <noscript>
                <p>' . $noscript . '</p>
            </noscript>';
            return $image;
        } else {
            return $this->remote_fallback($view, $media, $accessURL);
        }
    }

    public function remote_video_audio(PhpRenderer $view, MediaRepresentation $media)
    {
        $view->headLink()->appendStylesheet('https://vjs.zencdn.net/7.11.4/video-js.css');
        $view->headLink()->appendStylesheet($view->assetUrl('css/audioVideo.css', 'FITModule'));
        $view->headScript()->appendFile('https://vjs.zencdn.net/7.11.4/video.min.js', 'text/javascript', ['defer' => 'defer']);
        $youtubeID = array_key_exists('YouTubeID', $media->mediaData()) ? $media->mediaData()['YouTubeID'] : '';
        $vimeoID = array_key_exists('vimeoID', $media->mediaData()) ? $media->mediaData()['vimeoID'] : '';
        $googledriveID = array_key_exists('GoogleDriveID', $media->mediaData()) ? $media->mediaData()['GoogleDriveID'] : '';
        $accessURL = array_key_exists('access', $media->mediaData()) ? $media->mediaData()['access'] : '';

        if ($youtubeID != '') {
            $url = sprintf('https://www.youtube.com/embed/%s', $youtubeID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-video">
                <iframe class="embed-responsive-item" src="%s" allowfullscreen></iframe>
              </div>',
                $url
            );
            return $embed;
        } elseif ($vimeoID != '') {
            $url = sprintf('https://player.vimeo.com/video/%s', $vimeoID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-video">
                <iframe class="embed-responsive-item" src="%s" allowfullscreen></iframe>
              </div>',
                $url
            );
            return $embed;
        } elseif ($googledriveID != '') {
            $url = sprintf('https://drive.google.com/file/d/%s/preview', $googledriveID);
            $embed = sprintf(
                '<div class="embed-responsive embed-responsive-video">
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
            $referenceCode = '';
            $values = $media->value('dcterms:identifier', ['all' => true, 'type' => 'uri']);
            foreach ($values as $value) {
                if ($value->value() == "Reference Code") {
                    $referenceCode = $value->uri();
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
                            if ($relatedValue->value() == "Reference Code") {
                                $relatedReferenceCode = $relatedValue->uri();
                                if ($relatedReferenceCode == $referenceCode . "-captions") {
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
            // check if audio file
            $poster = '';
            $ext = pathinfo($accessURL, PATHINFO_EXTENSION);
            $mimes = new \Mimey\MimeTypes;
            if (strpos($mimes->getMimeType($ext), 'audio') === 0) {
                $poster = 'poster="' . $view->assetUrl('img/Speaker_Icon.svg', 'FITModule') . '"';
            }
            $video = sprintf(
                '<div class="embed-responsive embed-responsive-video">
                <video class="embed-responsive-item video-js vjs-big-play-centered" %s controls crossorigin="anonymous" data-setup=\'{"preload": "none"}\'>
                  <source src="%s">
                  %s
                </video>
              </div>',
                $poster,
                $videoURL,
                $captionHTML
            );
            return $video;
        } else {
            return $this->remote_fallback($view, $media, $accessURL);
        }
    }

    public function remote_pdf(PhpRenderer $view, MediaRepresentation $media, $accessURL = '')
    {
        $view->headLink()->appendStylesheet($view->assetUrl('css/pdf.css', 'FITModule'));
        $view->headScript()->appendFile('//cdnjs.cloudflare.com/ajax/libs/pdfobject/2.2.6/pdfobject.min.js', 'text/javascript');
        $pdfURL = $view->s3presigned($accessURL);
        $thumbnail = $view->thumbnail($media, 'medium');
        $title = $media->displayTitle();
        $pdfViewer =
        '<div class="pdf-container">
          <div class="loader"></div>
          <div id="pdf-' . $media->id() . '"></div>
        </div>

        <script>
        var options = {
          title: "' . $title . '",
          forcePDFJS: true,
          PDFJS_URL: "' . $view->assetUrl('js/pdfjs/web/viewer.html', 'FITModule', false, false) . '"
        };

        var myPDF = PDFObject.embed("' . $pdfURL . '", "#pdf-' . $media->id() . '", options);
        $(myPDF).on( "load", function() {
          $(".pdf-container .loader").remove();
        })
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
            return $hyperlink->raw($thumbnail, $driveURL);
        } else {
            return $thumbnail;
        }
    }
}
