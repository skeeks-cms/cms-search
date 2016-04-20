<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (ÑêèêÑ)
 * @date 15.04.2016
 */
namespace skeeks\cms\search\models;

use skeeks\cms\models\behaviors\Serialize;
use skeeks\cms\models\CmsSite;
use Yii;
use yii\helpers\ArrayHelper;
use yii\web\Request;

/**
 * This is the model class for table "{{%cms_search_phrase}}".
 *
 * @property integer $id
 * @property integer $created_by
 * @property integer $updated_by
 * @property integer $created_at
 * @property integer $updated_at
 * @property string $phrase
 * @property integer $result_count
 * @property integer $pages
 * @property string $ip
 * @property string $site_code
 * @property string $data_server
 * @property string $data_session
 * @property string $data_cookie
 * @property string $data_request
 * @property string $session_id
 *
 * @property CmsSite $site
 */
class CmsSearchPhrase extends \skeeks\cms\models\Core
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%cms_search_phrase}}';
    }

    /**
     * @return array
     */
    public function behaviors()
    {
        return array_merge(parent::behaviors(), [

            Serialize::className() =>
            [
                'class' => Serialize::className(),
                'fields' => ['data_server', 'data_session', 'data_cookie', 'data_request']
            ],

        ]);
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['created_by', 'updated_by', 'created_at', 'updated_at', 'result_count', 'pages'], 'integer'],
            [['data_server', 'data_session', 'data_cookie', 'data_request'], 'string'],
            [['phrase'], 'string', 'max' => 255],
            [['ip'], 'string', 'max' => 32],
            [['site_code'], 'string', 'max' => 15],

            ['data_request', 'default', 'value' => $_REQUEST],
            ['data_server', 'default', 'value' => $_SERVER],
            ['data_cookie', 'default', 'value' => $_COOKIE],
            ['data_session', 'default', 'value' => function(self $model, $attribute)
            {
                \Yii::$app->session->open();
                return $_SESSION;
            }],
            ['session_id', 'default', 'value' => function(self $model, $attribute)
            {
                \Yii::$app->session->open();
                return \Yii::$app->session->id;
            }],

            [['site_code'], 'default', 'value' => function(self $model, $attribute)
            {
                if (\Yii::$app->cms->site)
                {
                    return \Yii::$app->cms->site->code;
                }

                return null;
            }],

            ['ip', 'default', 'value' => \Yii::$app->request->userIP],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return array_merge(parent::attributeLabels(), [
            'id' => Yii::t('skeeks/search', 'ID'),
            'session_id' => Yii::t('skeeks/search', 'Session ID'),
            'created_by' => Yii::t('skeeks/search', 'Created By'),
            'updated_by' => Yii::t('skeeks/search', 'Updated By'),
            'created_at' => Yii::t('skeeks/search', 'Created At'),
            'updated_at' => Yii::t('skeeks/search', 'Updated At'),
            'phrase' => Yii::t('skeeks/search', 'Search Phrase'),
            'result_count' => Yii::t('skeeks/search', 'Documents Found'),
            'pages' => Yii::t('skeeks/search', 'Pages Count'),
            'ip' => Yii::t('skeeks/search', 'Ip'),
            'site_code' => Yii::t('skeeks/search', 'Site'),
            'data_server' => Yii::t('skeeks/search', 'Data Server'),
            'data_session' => Yii::t('skeeks/search', 'Data Session'),
            'data_cookie' => Yii::t('skeeks/search', 'Data Cookie'),
            'data_request' => Yii::t('skeeks/search', 'Data Request'),
        ]);
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getSite()
    {
        return $this->hasOne(CmsSite::className(), ['code' => 'site_code']);
    }
}