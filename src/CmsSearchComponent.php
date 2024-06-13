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
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentElementProperty;
use skeeks\cms\models\CmsContentPropertyEnum;
use skeeks\cms\search\assets\CmsSearchAsset;
use yii\base\Event;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
use yii\web\Application;
use yii\web\NotFoundHttpException;
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
            'name'  => \Yii::t('skeeks/search', 'Searching'),
            'image' => [
                CmsSearchAsset::class,
                'icons/computer-icons-youtube-location.jpg',
            ],
        ]);
    }


    public function init()
    {
        if (\Yii::$app instanceof Application) {
            //if (YII_ENV_DEV) {

            //Хитрая защита от спама
            $searchCookieKey = "_sx_s";
            $searchCacheKey = "_sx_s";

            if (!$key = \Yii::$app->cache->get($searchCacheKey)) {
                $key = \Yii::$app->security->generateRandomString(5);
                \Yii::$app->cache->set($searchCacheKey, $key, 60*5);
            }

            if (\Yii::$app->controller->uniqueId == "cmsSearch/result") {
                  //Проверить куку
                $cookies = \Yii::$app->request->cookies;
                $cookieKey = $cookies->getValue($searchCookieKey);
                if ($cookieKey != $key) {
                    throw new NotFoundHttpException();
                }

            } else {

                //Установить скрытую куку

                $cookieKey = \Yii::$app->request->cookies->getValue($searchCookieKey);

                if (!$cookieKey) {
                    // добавление новой куки в HTTP-ответ
                    \Yii::$app->response->cookies->add(new \yii\web\Cookie([
                        'name' => $searchCookieKey,
                        'value' => $key,
                    ]));
                } else {
                    if ($cookieKey != $key) {

                        \Yii::$app->response->cookies->add(new \yii\web\Cookie([
                            'name' => $searchCookieKey,
                            'value' => $key,
                        ]));
                    }

                }
            }
            //}
        }
        return parent::init();
    }

    public $searchElementContentIds = [];

    public $searchElementFields =
        [
            'description_full',
            'description_short',
            'name',
            'external_id',
        ];
    public $enabledElementProperties = 'Y';
    public $enabledElementPropertiesSearchable = 'Y';
    public $enabledElementParentNotNull = 'N';

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
            [['enabledElementParentNotNull'], 'safe'],
        ]);
    }

    public function attributeLabels()
    {
        return ArrayHelper::merge(parent::attributeLabels(), [
            'searchQueryParamName'               => \Yii::t('skeeks/search', 'Setting the search query in the address bar'),
            'searchElementFields'                => \Yii::t('skeeks/search',
                'The main elements of a set of fields on which to search'),
            'enabledElementProperties'           => \Yii::t('skeeks/search', 'Search among items of additional fields'),
            'enabledElementPropertiesSearchable' => \Yii::t('skeeks/search',
                'Consider the setting of additional fields in the search for him'),
            'enabledElementParentNotNull' => \Yii::t('skeeks/search',
                'Search elements who have parent element'),
            'searchElementContentIds'            => \Yii::t('skeeks/search', 'Search for content items of the following types'),
            'phraseLiveTime'                     => \Yii::t('skeeks/search', 'Time storage searches'),
        ]);
    }

    public function attributeHints()
    {
        return ArrayHelper::merge(parent::attributeHints(), [
            'searchQueryParamName'               => \Yii::t('skeeks/search', 'Parameter name for the address bar'),
            'phraseLiveTime'                     => \Yii::t('skeeks/search', 'If you specify 0, the searches will not be deleted ever'),
            'enabledElementProperties'           => \Yii::t('skeeks/search',
                'Including this option, the search begins to take into account the additional elements of the field'),
            'enabledElementPropertiesSearchable' => \Yii::t('skeeks/search',
                'Each additional feature is its customization. This option will include a search not for any additional properties, but only with the option "Property values are involved in the search for"'),
            'enabledElementParentNotNull' => \Yii::t('skeeks/search',
                'This option will include a search content child elements'),
        ]);
    }


    public function renderConfigFormFields(ActiveForm $form)
    {
        $result = $form->fieldSet(\Yii::t('skeeks/search', 'Main'));

        $result .= $form->field($this, 'searchQueryParamName');
        $result .= $form->field($this, 'phraseLiveTime');

        $result .= $form->fieldSetEnd();


        $result .= $form->fieldSet(\Yii::t('skeeks/search', 'Finding Items'));

        $result .= $form->fieldSelectMulti($this, 'searchElementContentIds', CmsContent::getDataForSelect());
        $result .= $form->fieldSelectMulti($this, 'searchElementFields',
            (new \skeeks\cms\models\CmsContentElement())->attributeLabels());
        $result .= $form->field($this, 'enabledElementParentNotNull')->checkbox([
            'uncheck' => \skeeks\cms\components\Cms::BOOL_N,
            'value'   => \skeeks\cms\components\Cms::BOOL_Y,
        ]);
        $result .= $form->field($this, 'enabledElementProperties')->checkbox([
            'uncheck' => \skeeks\cms\components\Cms::BOOL_N,
            'value'   => \skeeks\cms\components\Cms::BOOL_Y,
        ]);
        $result .= $form->field($this, 'enabledElementPropertiesSearchable')->checkbox([
            'uncheck' => \skeeks\cms\components\Cms::BOOL_N,
            'value'   => \skeeks\cms\components\Cms::BOOL_Y,
        ]);

        $result .= $form->fieldSetEnd();

        $result .= $form->fieldSet(\Yii::t('skeeks/search', 'Search sections'));
        $result .= \Yii::t('skeeks/search', 'In developing');
        $result .= $form->fieldSetEnd();

        return $result;
    }

    /**
     * @return string
     */
    public function getSearchQuery()
    {
        if (!$query = (string)\Yii::$app->request->get($this->searchQueryParamName)) {
            $query = (string)\Yii::$app->request->post($this->searchQueryParamName);
        }
        $query = htmlspecialchars($query, ENT_QUOTES, 'UTF-8');

        return $query;
    }

    /**
     * Конфигурирование объекта запроса поиска по элементам.
     *
     * @param \yii\db\ActiveQuery $activeQuery
     * @param null                $modelClassName
     * @return $this
     */
    public function buildElementsQuery(\yii\db\ActiveQuery $activeQuery)
    {
        $where = [];
        
        if (!\Yii::$app->request->referrer) {
            throw new NotFoundHttpException("Поисковый запрос некорректный");
        }

        $searchQueryArr = explode(" ", $this->searchQuery);
        if ($searchQueryArr) {
            foreach ($searchQueryArr as $key => $value) {
                $value = trim($value);
                if (!$value) {
                    unset($searchQueryArr[$key]);
                } else {
                    $searchQueryArr[$key] = $value;
                }
            }
        }
        //Нужно учитывать связанные дополнительные данные
        if ($this->enabledElementProperties == Cms::BOOL_Y) {
            $activeQuery->joinWith('cmsContentElementProperties');

            //Нужно учитывать настройки связанные дополнительные данных
            if ($this->enabledElementPropertiesSearchable == Cms::BOOL_Y) {
                $activeQuery->joinWith('cmsContentElementProperties.property');
                /*$activeQuery->joinWith('cmsContentElementProperties.valueEnum');
                $activeQuery->joinWith('cmsContentElementProperties.valueElement');*/

                $tmpWhere = [];
                foreach ($searchQueryArr as $value) {
                    $tmpWhere[] = [
                        'or',
                        ['like', CmsContentElementProperty::tableName() . ".value", '%'.$value.'%', false],
                        //['like', CmsContentPropertyEnum::tableName() . ".value", '%'.$value.'%', false],
                        //[CmsContentProperty::tableName() . ".searchable" => Cms::BOOL_Y]
                    ];
                }
                $where[] = array_merge(['and'], $tmpWhere);

            } else {

                $tmpWhere = [];
                foreach ($searchQueryArr as $value) {
                    $tmpWhere[] = [
                        'like',
                        CmsContentElementProperty::tableName().".value",
                        '%'.$value.'%',
                        false,
                    ];
                }
                $where[] = array_merge(['and'], $tmpWhere);

            }
        }

        //Поиск по основному набору полей
        if ($this->searchElementFields) {
            foreach ($this->searchElementFields as $fieldName) {
                $tmpWhere = [];
                foreach ($searchQueryArr as $value) {
                    $tmpWhere[] = [
                        'like',
                        CmsContentElement::tableName().".".$fieldName,
                        '%'.$value.'%',
                        false,
                    ];
                }

                $where[] = array_merge(['and'], $tmpWhere);
            }
        }

        if ($where) {
            $where = array_merge(['or'], $where);
            $activeQuery->andWhere($where);
        }

        //Нужно учитывать дочерние элементы контента
        if ($this->enabledElementParentNotNull != Cms::BOOL_Y) {
            $activeQuery->andWhere([CmsContentElement::tableName().'.parent_content_element_id' => null]);
        }
        //Отфильтровать только конкретный тип
        if ($this->searchElementContentIds) {
            $activeQuery->andWhere([
                CmsContentElement::tableName().".content_id" => (array)$this->searchElementContentIds,
            ]);
        }

        return $this;
    }

    /**
     * @param ActiveDataProvider $dataProvider
     */
    public function logResult(ActiveDataProvider $dataProvider)
    {
        //todo:временно отключим
        return false;
        
        $pages = 1;

        if ($dataProvider->totalCount > $dataProvider->pagination->pageSize) {
            $pages = round($dataProvider->totalCount / $dataProvider->pagination->pageSize);
        }

        $searchPhrase = new \skeeks\cms\search\models\CmsSearchPhrase([
            'phrase'       => $this->searchQuery,
            'result_count' => $dataProvider->totalCount,
            'pages'        => $pages,
        ]);

        $searchPhrase->save();
    }
}