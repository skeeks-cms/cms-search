<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 19.03.2015
 */
namespace skeeks\cms\search;
use skeeks\cms\assets\CmsToolbarAsset;
use skeeks\cms\assets\CmsToolbarAssets;
use skeeks\cms\assets\CmsToolbarFancyboxAsset;
use skeeks\cms\components\Cms;
use skeeks\cms\helpers\UrlHelper;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentElementProperty;
use skeeks\cms\models\CmsContentProperty;
use skeeks\cms\search\models\CmsSearchPhrase;
use skeeks\cms\modules\admin\controllers\AdminModelEditorController;
use skeeks\cms\rbac\CmsManager;
use yii\base\BootstrapInterface;
use yii\data\ActiveDataProvider;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\web\Application;
use yii\web\View;

use \Yii;
use yii\widgets\ActiveForm;

/**
 * @property string searchQuery
 *
 * Class CmsSearchComponent
 * @package skeeks\cms\search
 */
class CmsSearchComponent extends \skeeks\cms\base\Component
{
    /**
     * Можно задать название и описание компонента
     * @return array
     */
    static public function descriptorConfig()
    {
        return array_merge(parent::descriptorConfig(), [
            'name'          => \Yii::t('skeeks/search', 'Searching'),
        ]);
    }

    public $searchElementContentIds = [];

    public $searchElementFields =
    [
        'description_full',
        'description_short',
        'name',
    ];
    public $enabledElementProperties              = 'Y';
    public $enabledElementPropertiesSearchable    = 'Y';

    public $searchQueryParamName = "q";

    public $phraseLiveTime = 0;


    public function rules()
    {
        return ArrayHelper::merge(parent::rules(), [
            [['searchQueryParamName'], 'string'],
            [['enabledElementProperties'], 'string'],
            [['enabledElementPropertiesSearchable'], 'string'],
            [['phraseLiveTime'], 'integer'],
            [['searchElementFields'], 'safe'],
            [['searchElementContentIds'], 'safe'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'searchQueryParamName'                  => \Yii::t('skeeks/search','Setting the search query in the address bar'),
            'searchElementFields'                   => \Yii::t('skeeks/search','The main elements of a set of fields on which to search'),
            'enabledElementProperties'              => \Yii::t('skeeks/search','Search among items of additional fields'),
            'enabledElementPropertiesSearchable'    => \Yii::t('skeeks/search','Consider the setting of additional fields in the search for him'),
            'searchElementContentIds'               => \Yii::t('skeeks/search','Search for content items of the following types'),
            'phraseLiveTime'                        => \Yii::t('skeeks/search','Time storage searches'),
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'searchQueryParamName'              => \Yii::t('skeeks/search','Parameter name for the address bar'),
            'phraseLiveTime'                    => \Yii::t('skeeks/search','If you specify 0, the searches will not be deleted ever'),
            'enabledElementProperties'          => \Yii::t('skeeks/search','Including this option, the search begins to take into account the additional elements of the field'),
            'enabledElementPropertiesSearchable'=> \Yii::t('skeeks/search','Each additional feature is its customization. This option will include a search not for any additional properties, but only with the option "Property values are involved in the search for"'),
        ]);
    }


    public function renderConfigForm(ActiveForm $form)
    {
        echo $form->fieldSet(\Yii::t('skeeks/search', 'Main'));

            echo $form->field($this, 'searchQueryParamName');
            echo $form->fieldInputInt($this, 'phraseLiveTime');

        echo $form->fieldSetEnd();


        echo $form->fieldSet(\Yii::t('skeeks/search', 'Finding Items'));

            echo $form->fieldSelectMulti($this, 'searchElementContentIds', CmsContent::getDataForSelect() );
            echo $form->fieldSelectMulti($this, 'searchElementFields', (new \skeeks\cms\models\CmsContentElement())->attributeLabels() );
            echo $form->fieldRadioListBoolean($this, 'enabledElementProperties');
            echo $form->fieldRadioListBoolean($this, 'enabledElementPropertiesSearchable');

        echo $form->fieldSetEnd();

        echo $form->fieldSet(\Yii::t('skeeks/search','Search sections'));
            echo \Yii::t('skeeks/search','In developing');
        echo $form->fieldSetEnd();
    }

    /**
     * @return string
     */
    public function getSearchQuery()
    {
        return (string) \Yii::$app->request->get($this->searchQueryParamName);
    }

    /**
     * Конфигурирование объекта запроса поиска по элементам.
     *
     * @param \yii\db\ActiveQuery $activeQuery
     * @param null $modelClassName
     * @return $this
     */
    public function buildElementsQuery(\yii\db\ActiveQuery $activeQuery)
    {
        $where = [];

        //Нужно учитывать связанные дополнительные данные
        if ($this->enabledElementProperties == Cms::BOOL_Y)
        {
            $activeQuery->joinWith('cmsContentElementProperties');

            //Нужно учитывать настройки связанные дополнительные данных
            if ($this->enabledElementPropertiesSearchable == Cms::BOOL_Y)
            {
                $activeQuery->joinWith('cmsContentElementProperties.property');

                $where[] = ['and',
                    ['like', CmsContentElementProperty::tableName() . ".value", '%' . $this->searchQuery . '%', false],
                    //[CmsContentProperty::tableName() . ".searchable" => Cms::BOOL_Y]
                ];
            } else
            {
                $where[] = ['like', CmsContentElementProperty::tableName() . ".value", '%' . $this->searchQuery . '%', false];
            }
        }

        //Поиск по основному набору полей
        if ($this->searchElementFields)
        {
            foreach ($this->searchElementFields as $fieldName)
            {
                $where[] = ['like', CmsContentElement::tableName() . "." . $fieldName, '%' . $this->searchQuery . '%', false];
            }
        }

        if ($where)
        {
            $where = array_merge(['or'], $where);
            $activeQuery->andWhere($where);
        }

        //Отфильтровать только конкретный тип
        if ($this->searchElementContentIds)
        {
            $activeQuery->andWhere([
                CmsContentElement::tableName() . ".content_id" => (array) $this->searchElementContentIds
            ]);
        }

        return $this;
    }

    /**
     * @param ActiveDataProvider $dataProvider
     */
    public function logResult(ActiveDataProvider $dataProvider)
    {
        $pages = 1;

        if ($dataProvider->totalCount > $dataProvider->pagination->pageSize)
        {
            $pages = round($dataProvider->totalCount / $dataProvider->pagination->pageSize);
        }

        $searchPhrase = new \skeeks\cms\search\models\CmsSearchPhrase([
            'phrase'        => $this->searchQuery,
            'result_count'  => $dataProvider->totalCount,
            'pages'         => $pages,
        ]);

        $searchPhrase->save();
    }
}