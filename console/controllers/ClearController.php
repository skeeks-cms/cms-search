<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */
namespace skeeks\cms\search\console\controllers;
use skeeks\cms\search\models\CmsSearchPhrase;
use yii\console\Controller;


/**
 * Remove old searches
 * @package skeeks\cms\console\controllers
 */
class ClearController extends Controller
{
    public $defaultAction = 'phrase';
    /**
     * Remove old searches
     */
    public function actionPhrase()
    {
        $this->stdout('phraseLiveTime: ' . \Yii::$app->cmsSearch->phraseLiveTime . "\n");

        if (\Yii::$app->cmsSearch->phraseLiveTime)
        {
            $deleted = CmsSearchPhrase::deleteAll([
                '<=', 'created_at', \Yii::$app->formatter->asTimestamp(time()) - (int) \Yii::$app->cmsSearch->phraseLiveTime
            ]);

            $message = \Yii::t('skeeks/search', 'Removing searches') . " :" . $deleted;
            \Yii::info($message, 'skeeks/search');
            $this->stdout("\t" . $message . "\n");
        }
    }

}