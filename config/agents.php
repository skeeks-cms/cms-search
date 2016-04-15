<?php
return [
    'cmsSearch/clear/phrase' =>
    [
        'description'       => 'Чистка поисковых запросов',
        'agent_interval'    => 3600*24, //раз в сутки
        'next_exec_at'      => \Yii::$app->formatter->asTimestamp(time()) + 3600*24,
        'is_period'         => 'N'
    ]
];