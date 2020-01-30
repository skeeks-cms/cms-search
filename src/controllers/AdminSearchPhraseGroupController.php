<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\search\controllers;

use skeeks\cms\models\CmsAgent;
use skeeks\cms\models\CmsSearchPhrase;
use skeeks\cms\modules\admin\actions\AdminAction;
use skeeks\cms\modules\admin\controllers\AdminController;
use yii\helpers\ArrayHelper;

/**
 * @author Semenov Alexander <semenov@skeeks.com>
 */
class AdminSearchPhraseGroupController extends AdminController
{
    public function init()
    {
        $this->name = \Yii::t('skeeks/search', "Jump list");

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return ArrayHelper::merge(parent::actions(),
            [
                'index' =>
                    [
                        'class'    => AdminAction::className(),
                        "icon"     => "glyphicon glyphicon-th-list",
                        "priority" => 0,
                        "name"     => \Yii::t('skeeks/search', 'List'),
                    ],
            ]
        );
    }

}
