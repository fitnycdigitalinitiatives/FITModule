<?php
namespace FITModule\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Aws\S3\S3Client;
use Aws\Exception\AwsException;

/**
 * View helper for rendering a title heading for a page.
 */
class FITModuleS3Presigned extends AbstractHelper
{
    /**
     * Return a presigned URL for S3 object.
     */
    public function __invoke($url)
    {
        if (strpos($url, 'amazonaws') !== false) {
            $parsed_url = parse_url($url);
            $subdomains = explode(".", $parsed_url["host"]);
            if ((array_key_exists(0, $subdomains)) && (array_key_exists(2, $subdomains))) {
                $key = ltrim($parsed_url["path"], '/');
                $bucket = $subdomains[0];
                $region = $subdomains[2];
                if ((!$key) || (!$bucket) || (!$region)) {
                    //if anything is blank, just return original url, because it's not valid
                    return $url;
                }
                $view = $this->getView();

                // Check if S3 Connection is turned on
                if (!$view->setting('fit_module_s3_connection')) {
                    //if it isn't activated, just return the original url because it's probably not s3
                    return $url;
                }

                // Set up AWS Client
                $s3Client = new S3Client([
                    'version' => 'latest',
                    'region' => $region,
                    'credentials' => [
                        'key' => $view->setting('fit_module_aws_key'),
                        'secret' => $view->setting('fit_module_aws_secret_key'),
                    ],
                ]);
                $params = [
                    'Bucket' => $bucket,
                    'Key' => $key
                ];
                if (pathinfo($key, PATHINFO_EXTENSION) == 'pdf') {
                    $params['ResponseContentType'] = 'application/pdf';
                }
                $cmd = $s3Client->getCommand('GetObject', $params);
                $request = $s3Client->createPresignedRequest($cmd, '+180 minutes');
                $presignedUrl = (string) $request->getUri();
                return $presignedUrl;
            } else {
                return $url;
            }
        } else {
            return $url;
        }
    }
}