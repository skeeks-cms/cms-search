<?php
namespace skeeks\cms\search\console\controllers;

use skeeks\cms\search\services\PhraseCleanup;
use yii\console\Controller;
use yii\console\ExitCode;

/** Backwards-compatible CLI entry point; the queue calls the service directly. */
class ClearController extends Controller
{
    public $defaultAction = 'phrase';

    public function actionPhrase()
    {
        $lifetime = (int)\Yii::$app->cmsSearch->phraseLiveTime;
        $this->stdout('phraseLiveTime: '.$lifetime."\n");
        $result = \Yii::createObject(PhraseCleanup::class)->run($lifetime);
        if ($result['enabled']) {
            $message = \Yii::t('skeeks/search', 'Removing searches').' :'.$result['deleted'];
            \Yii::info($message, 'skeeks/search');
            $this->stdout("\t".$message."\n");
        }
        return ExitCode::OK;
    }
}