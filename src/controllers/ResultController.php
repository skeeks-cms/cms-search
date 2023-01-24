<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\search\controllers;

use skeeks\cms\base\Controller;
use skeeks\cms\helpers\StringHelper;
use skeeks\cms\search\models\CmsContentElement;
use skeeks\cms\search\models\CmsSearchPhrase;
use skeeks\cms\models\Tree;
use Yii;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\web\Response;

/**
 * Class SearchController
 * @package skeeks\cms\controllers
 */
class ResultController extends Controller
{
    /**
     * @return string
     */
    public function actionIndex()
    {
        /*\Yii::$app->seo->canUrlEnableDefaultControllers = ArrayHelper::merge(\Yii::$app->seo->canUrlEnableDefaultControllers, [
            'cmsSearch/result'
        ]);*/


        $searchQuery = \Yii::$app->cmsSearch->searchQuery;
        $this->view->title = StringHelper::ucfirst($searchQuery) . " — результаты поиска";

        return $this->render($this->action->id);
    }
}
