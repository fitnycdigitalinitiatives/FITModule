<?php
namespace FITModule\Form;

use Laminas\Form\Form;
use Laminas\Form\Element;

class ConfigForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'aws_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'AWS key for S3 buckets', // @translate
            ],
            'attributes' => [
                'id' => 'aws_key',
            ],
        ]);
        $this->add([
            'name' => 'aws_secret_key',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'AWS secret key for S3 buckets', // @translate
            ],
            'attributes' => [
                'id' => 'aws_secret_key',
            ],
        ]);
        $this->add([
            'name' => 's3_region',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'S3 bucket region, e.g. "us-east-1".', // @translate
            ],
            'attributes' => [
                'id' => 's3_region',
            ],
        ]);
    }
}
