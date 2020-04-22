<?php
return [
    'media_ingesters' => [
        'factories' => [
            'mymodule_tweet' => Service\Media\Ingester\TweetFactory::class,
        ],
    ],
    'media_renderers' => [
        'invokables' => [
            'mymodule_tweet' => Media\Renderer\Tweet::class,
        ],
    ],
];
