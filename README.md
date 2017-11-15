Component search for SkeekS CMS
===================================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist skeeks/cms-search "*"
```

or add

```
"skeeks/cms-search": "*"
```

Configuration app
----------

```php

'components' =>
[
    'cmsSearch' => [
        'class'     => 'skeeks\cms\search\CmsSearchComponent',
    ],

    'i18n' => [
        'translations' =>
        [
            'skeeks/search' => [
                'class'             => 'yii\i18n\PhpMessageSource',
                'basePath'          => '@skeeks/cms/search/messages',
                'fileMap' => [
                    'skeeks/search' => 'main.php',
                ],
            ]
        ]
    ],

    /*'urlManager' => [
        'rules' => [
            'search'                                => 'cmsSearch/result',
        ]
    ]*/
],

'modules' =>
[
    'cmsSearch' => [
        'class'         => 'skeeks\cms\search\CmsSearchModule',
    ]
]

```

##Links
* [Web site](https://cms.skeeks.com)
* [Author](https://skeeks.com)
* [ChangeLog](https://github.com/skeeks-cms/cms-search/blob/master/CHANGELOG.md)


___

> [![skeeks!](https://skeeks.com/img/logo/logo-no-title-80px.png)](https://skeeks.com)  
<i>SkeekS CMS (Yii2) — quickly, easily and effectively!</i>  
[skeeks.com](https://skeeks.com) | [cms.skeeks.com](https://cms.skeeks.com)

