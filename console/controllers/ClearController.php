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
    /**
     * Remove old searches
     */
    public function actionPhrase()
    {
        if (\Yii::$app->cmsSearch->phraseLiveTime)
        {
            $deleted = CmsSearchPhrase::deleteAll([
                '<=', 'created_at', \Yii::$app->formatter->asTimestamp(time()) - (int) \Yii::$app->cmsSearch->phraseLiveTime
            ]);

            \Yii::info(\Yii::t('skeeks/search', 'Removing searches') . " :" . $deleted, 'skeeks/search');
        }
    }

}