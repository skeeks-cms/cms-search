<?php
return [

    'components' => [
        'jobRegistry' => [
            'types' => [
                'search.clear-phrases' => [
                    'type' => 'search.clear-phrases',
                    'title' => 'Чистка поисковых запросов',
                    'handler' => \skeeks\cms\search\jobs\PhraseCleanupJobHandler::class,
                    'queue' => 'maintenance',
                    'timeout' => 7200,
                    'leaseSeconds' => 120,
                    'maxAttempts' => 1,
                    'idempotent' => false,
                    'overlapPolicy' => 'skip',
                    'permission' => \skeeks\cms\rbac\CmsManager::PERMISSION_ROLE_ADMIN_ACCESS,
                    // This operation affects shared data across the installation's sites.
                    'resourceKey' => static function () { return 'search:clear-phrases'; },
                    'dedupKey' => static function () { return 'search:clear-phrases'; },
                ],
            ],
        ],

        'cmsAgent' => [
            'commands' => [
                'cmsSearch/clear/phrase' => [
                    'jobType' => 'search.clear-phrases',
                    'class' => \skeeks\cms\agent\CmsAgent::class,
                    'name' => 'Чистка поисковых запросов',
                    'interval' => 3600 * 24,
                ],
            ]
        ],
    ],

];
