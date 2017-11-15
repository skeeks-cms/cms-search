<?php
return [

    'components' => [
        'cmsSearch' => [
            'class' => 'skeeks\cms\search\CmsSearchComponent',
        ],

        'i18n' => [
            'translations' =>
                [
                    'skeeks/search' => [
                        'class' => 'yii\i18n\PhpMessageSource',
                        'basePath' => '@skeeks/cms/search/messages',
                        'fileMap' => [
                            'skeeks/search' => 'main.php',
                        ],
                    ]
                ]
        ],
    ],

    'modules' => [
        'cmsSearch' => [
            'class' => 'skeeks\cms\search\CmsSearchModule',
            "controllerNamespace" => 'skeeks\cms\search\console\controllers'
        ]
    ]
];