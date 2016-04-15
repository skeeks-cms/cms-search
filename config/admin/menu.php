<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */
return
[
    'other' =>
    [
        'items' =>
        [
            [
                "label"     => \Yii::t('skeeks/search', "Searching"),
                "img"       => ['\skeeks\cms\search\assets\CmsSearchAsset', 'icons/search.png'],

                'items' =>
                [
                    [
                        "label" => \Yii::t('app', "Settings"),
                        "url"   => ["cms/admin-settings", "component" => 'skeeks\cms\search\CmsSearchComponent'],
                        "img"       => ['\skeeks\cms\modules\admin\assets\AdminAsset', 'images/icons/settings-big.png'],
                        "activeCallback"       => function(\skeeks\cms\modules\admin\helpers\AdminMenuItem $adminMenuItem)
                        {
                            return (bool) (\Yii::$app->request->getUrl() == $adminMenuItem->getUrl());
                        },
                    ],

                    [
                        "label"     => \Yii::t('skeeks/search',"Statistic"),
                        "img"       => ['\skeeks\cms\search\assets\CmsSearchAsset', 'icons/statistics.png'],

                        'items' =>
                        [
                            [
                                "label" => \Yii::t('skeeks/search',"Jump list"),
                                "url"   => ["cmsSearch/admin-search-phrase"],
                            ],

                            [
                                "label" => \Yii::t('skeeks/search',"Phrase list"),
                                "url"   => ["cmsSearch/admin-search-phrase-group"],
                            ],
                        ],
                    ],
                ],
            ],
        ]
    ]
];