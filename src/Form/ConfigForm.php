<?php

namespace FITModule\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 's3_connection',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Activate Remote Connection AWS S3', // @translate
            ],
            'attributes' => [
                'id' => 's3_connection',
            ],
        ]);
        $this->add([
            'name' => 'aws_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'AWS key for S3 buckets/DynamoDB', // @translate
            ],
            'attributes' => [
                'id' => 'aws_key',
            ],
        ]);
        $this->add([
            'name' => 'aws_secret_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'AWS secret key for S3 buckets/DynamoDB', // @translate
            ],
            'attributes' => [
                'id' => 'aws_secret_key',
            ],
        ]);
        $this->add([
            'name' => 'aws_iiif_endpoint',
            'type' => Element\Url::class,
            'options' => [
                'label' => 'AWS Image Server IIIF Endpoint', // @translate
            ],
            'attributes' => [
                'id' => 'aws_iiif_endpoint',
            ],
        ]);
        $this->add([
            'name' => 'iiif_secret_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'JWT secret key for access to IIIF Server', // @translate
            ],
            'attributes' => [
                'id' => 'iiif_secret_key',
            ],
        ]);
        $this->add([
            'name' => 'aws_dynamodb_table',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'DynamoDB Table with item/media visibility info for IIIF authetication.', // @translate
            ],
            'attributes' => [
                'id' => 'aws_dynamodb_table',
            ],
        ]);
        $this->add([
            'name' => 'aws_dynamodb_table_region',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'DynamoDB Table region, ie "us-east-1".', // @translate
            ],
            'attributes' => [
                'id' => 'aws_dynamodb_table_region',
            ],
        ]);
        $this->add([
            'name' => 'solr_hostname',
            'type' => 'Text',
            'options' => [
                'label' => 'Solr Hostname (OCR)',
            ],
            'attributes' => [
                'id' => 'solr_hostname',
            ],
        ]);
        $this->add([
            'name' => 'solr_port',
            'type' => 'Text',
            'options' => [
                'label' => 'Solr Port (OCR)',
            ],
            'attributes' => [
                'id' => 'solr_port',
            ],
        ]);
        $this->add([
            'name' => 'solr_path',
            'type' => 'Text',
            'options' => [
                'label' => 'Solr Path (OCR)',
            ],
            'attributes' => [
                'id' => 'solr_path',
            ],
        ]);
        $this->add([
            'name' => 'solr_connection',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Activate connection to Solr for OCR full-text search/indexing',
                // @translate
            ],
            'attributes' => [
                'id' => 'solr_connection',
            ],
        ]);
        $this->add([
            'name' => 'solr_login',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Solr username (OCR)',
                // @translate
            ],
            'attributes' => [
                'id' => 'solr_login',
            ],
        ]);
        $this->add([
            'name' => 'solr_password',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Solr password (OCR)',
                // @translate
            ],
            'attributes' => [
                'id' => 'solr_password',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'aws_iiif_endpoint',
            'required' => false,
        ]);
    }
}
