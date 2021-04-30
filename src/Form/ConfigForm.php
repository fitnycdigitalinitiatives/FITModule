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
    }
}
