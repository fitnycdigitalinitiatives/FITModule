<?php
namespace FITModule\View\Helper;

use Zend\View\Helper\AbstractHelper;
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
        $parsed_url = parse_url($url);
        $key = ltrim($parsed_url["path"], '/');
        $bucket = explode(".", $parsed_url["host"])[0];
        $view = $this->getView();

        // Set up AWS Client
        $s3Client = new S3Client([
            'version'     => 'latest',
            'region'      => $view->setting('fit_module_s3_region'),
            'credentials' => [
                'key'    => $view->setting('fit_module_aws_key'),
                'secret' => $view->setting('fit_module_aws_secret_key'),
            ],
        ]);
        if ($s3Client->doesObjectExist($bucket, $key)) {
            $cmd = $s3Client->getCommand('GetObject', [
            'Bucket' => $bucket,
            'Key' => $key
          ]);
            $request = $s3Client->createPresignedRequest($cmd, '+60 minutes');
            $presignedUrl = (string)$request->getUri();
            return $presignedUrl;
        } else {
            return "error";
        }
    }
}
