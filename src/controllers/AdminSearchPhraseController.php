<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 15.04.2016
 */

namespace skeeks\cms\search\controllers;

use skeeks\cms\components\Cms;
use skeeks\cms\grid\CreatedByColumn;
use skeeks\cms\grid\SiteColumn;
use skeeks\cms\modules\admin\controllers\AdminModelEditorController;
use skeeks\cms\modules\admin\traits\AdminModelEditorStandartControllerTrait;
use skeeks\cms\search\models\CmsSearchPhrase;
use yii\helpers\ArrayHelper;

/**
 * Class AdminSearchPhraseController
 * @package skeeks\cms\controllers
 */
class AdminSearchPhraseController extends AdminModelEditorController
{
    use AdminModelEditorStandartControllerTrait;

    public function init()
    {
        $this->name = \Yii::t('skeeks/search', "Jump list");
        $this->modelShowAttribute = "phrase";
        $this->modelClassName = CmsSearchPhrase::className();

        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return ArrayHelper::merge(parent::actions(),
            [
                'create' =>
                    [
                        'isVisible' => false
                    ],

                'update' =>
                    [
                        'isVisible' => false
                    ],

                'index' =>
                    [
                        "columns" => [
                            'phrase',

                            [
                                'class' => \skeeks\cms\grid\DateTimeColumnData::className(),
                                'attribute' => "created_at"
                            ],

                            [
                                'attribute' => "result_count"
                            ],

                            [
                                'attribute' => "pages"
                            ],

                            [
                                'class' => SiteColumn::className(),
                                'visible' => false
                            ],

                            [
                                'class' => CreatedByColumn::className(),
                            ],

                        ],
                    ],

            ]
        );
    }

}
