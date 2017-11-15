<?php
return [

    'components' => [
        'cmsAgent' => [
            'commands' => [
                'cmsSearch/clear/phrase' => [
                    'class' => \skeeks\cms\agent\CmsAgent::class,
                    'name' => 'Чистка поисковых запросов',
                    'interval' => 3600 * 24,
                ],
            ]
        ],
    ],

];
