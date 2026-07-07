<?php
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 19.03.2015
 */

namespace skeeks\cms\search;

use skeeks\cms\assets\CmsAsset;
use skeeks\cms\assets\CmsToolbarAsset;
use skeeks\cms\assets\CmsToolbarAssets;
use skeeks\cms\assets\CmsToolbarFancyboxAsset;
use skeeks\cms\components\Cms;
use skeeks\cms\models\CmsContent;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentElementProperty;
use skeeks\cms\models\CmsContentPropertyEnum;
use yii\base\Event;
use yii\data\ActiveDataProvider;
use yii\helpers\ArrayHelper;
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
                CmsAsset::class,
                'images/icons/admin-menu/search.svg',
            ],
        ]);
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
            'searchQueryParamName'               => \Yii::t('skeeks/search', 'Setting the search query in the address bar'),
            'searchElementFields'                => \Yii::t('skeeks/search',
                'The main elements of a set of fields on which to search'),
            'enabledElementProperties'           => \Yii::t('skeeks/search', 'Search among items of additional fields'),
            'enabledElementPropertiesSearchable' => \Yii::t('skeeks/search',
                'Consider the setting of additional fields in the search for him'),
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
     * @return array
     */
    protected function getSearchQueryWords()
    {
        $result = [];
        foreach ((array)preg_split('/\s+/', $this->searchQuery) as $value) {
            $value = trim($value);
            if ($value === '') {
                continue;
            }

            $value = mb_strtolower($value);
            $latinValue = str_replace('х', 'x', $value);
            $normalizedValue = preg_replace('/[^\p{L}\p{N}]+/u', '', $latinValue);
            $variants = array_values(array_filter(array_unique([
                $value,
                $latinValue,
                $normalizedValue,
            ])));

            if (!$variants) {
                continue;
            }

            $result[] = [
                'variants' => $variants,
                'normalized' => $normalizedValue,
            ];
        }

        return $result;
    }


    public function _buildElementsQuery(\yii\db\ActiveQuery $activeQuery)
{
    if (!$this->searchQuery) {
        return $this;
    }

    // --- 1. Разбивка и нормализация
    $searchQueryArr = preg_split('/\s+/', $this->searchQuery);

    $searchQueryArr = array_values(array_filter(array_map(function ($value) {
        $value = trim($value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
        $value = str_replace('х', 'x', mb_strtolower($value));

        if (mb_strlen($value) < 2) {
            return null;
        }

        return $value;
    }, $searchQueryArr)));

    if (!$searchQueryArr) {
        return $this;
    }

    // --- 2. JOIN свойств (если нужно)
    if ($this->enabledElementProperties == Cms::BOOL_Y) {
        $activeQuery->joinWith('cmsContentElementProperties');
    }

    // --- 3. SKU (точный поиск)
    $skuConditions = ['or'];
    foreach ($searchQueryArr as $value) {
        $skuConditions[] = ['=', 'shopProduct.brand_sku', $value];
    }

    // --- 4. МЯГКИЙ текстовый поиск (OR)
    $textConditions = ['or'];

    foreach ($searchQueryArr as $value) {

        $wordBlock = ['or'];

        // поиск по основным полям
        if ($this->searchElementFields) {
            foreach ($this->searchElementFields as $fieldName) {
                $wordBlock[] = [
                    'like',
                    CmsContentElement::tableName() . "." . $fieldName,
                    $value,
                    false
                ];
            }
        }

        // поиск по свойствам
        if ($this->enabledElementProperties == Cms::BOOL_Y) {
            $wordBlock[] = [
                'like',
                CmsContentElementProperty::tableName() . ".value",
                $value,
                false
            ];
        }

        $textConditions[] = $wordBlock;
    }

    // --- 5. WHERE (SKU или текст)
    $activeQuery->andWhere([
        'or',
        $skuConditions,
        $textConditions
    ]);

    // --- 6. РЕЛЕВАНТНОСТЬ
    $relevanceParts = [];

    foreach ($searchQueryArr as $value) {

        // SKU — максимальный вес
        $relevanceParts[] = "IF(shopProduct.brand_sku = '{$value}', 1000, 0)";

        // основные поля
        if ($this->searchElementFields) {
            foreach ($this->searchElementFields as $fieldName) {
                $relevanceParts[] = "IF(" . CmsContentElement::tableName() . ".$fieldName LIKE '%{$value}%', 80, 0)";
            }
        }

        // свойства
        if ($this->enabledElementProperties == Cms::BOOL_Y) {
            $relevanceParts[] = "IF(" . CmsContentElementProperty::tableName() . ".value LIKE '%{$value}%', 30, 0)";
        }
    }

    $relevanceSql = implode(' + ', $relevanceParts);

    // добавляем relevance в SELECT
    $activeQuery->addSelect([
        CmsContentElement::tableName() . '.*',
        "({$relevanceSql}) AS relevance"
    ]);

    // --- 7. Фильтр по релевантности (вместо HAVING)
    $activeQuery->andWhere("({$relevanceSql}) > 0");

    // --- 8. Сортировка
    $activeQuery->orderBy(['relevance' => SORT_DESC]);

    // --- 9. Базовые фильтры
    $activeQuery->andWhere([
        CmsContentElement::tableName() . '.parent_content_element_id' => null
    ]);

    if ($this->searchElementContentIds) {
        $activeQuery->andWhere([
            CmsContentElement::tableName() . ".content_id" => (array)$this->searchElementContentIds,
        ]);
    }

    return $this;
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
        $searchQueryArr = $this->getSearchQueryWords();

        if (!$searchQueryArr) {
            return $this;
        }

        $conditionIndex = 0;

        if ($this->enabledElementProperties == Cms::BOOL_Y) {
            $activeQuery->joinWith('cmsContentElementProperties');

            if ($this->enabledElementPropertiesSearchable == Cms::BOOL_Y) {
                $activeQuery->joinWith('cmsContentElementProperties.property');
            }

            $tmpWhere = [];
            foreach ($searchQueryArr as $searchWord) {
                $wordWhere = ['or'];
                foreach ($searchWord['variants'] as $value) {
                    $wordWhere[] = [
                        'like',
                        CmsContentElementProperty::tableName().".value",
                        '%'.$value.'%',
                        false,
                    ];
                }

                if ($searchWord['normalized']) {
                    $conditionIndex++;
                    $paramName = ':sxSearchPropertyNormalized'.$conditionIndex;
                    $wordWhere[] = new \yii\db\Expression(
                        "REPLACE(REPLACE(".CmsContentElementProperty::tableName().".value, '-', ''), ' ', '') LIKE {$paramName}",
                        [$paramName => '%'.$searchWord['normalized'].'%']
                    );
                }

                $tmpWhere[] = $wordWhere;
            }
            $where[] = array_merge(['and'], $tmpWhere);
        }

        if ($this->searchElementFields) {
            foreach ($this->searchElementFields as $fieldName) {
                $tmpWhere = [];
                foreach ($searchQueryArr as $searchWord) {
                    $columnName = CmsContentElement::tableName().".".$fieldName;
                    $wordWhere = ['or'];
                    foreach ($searchWord['variants'] as $value) {
                        $wordWhere[] = [
                            'like',
                            $columnName,
                            '%'.$value.'%',
                            false,
                        ];
                    }

                    if ($searchWord['normalized']) {
                        $conditionIndex++;
                        $paramName = ':sxSearchFieldNormalized'.$conditionIndex;
                        $wordWhere[] = new \yii\db\Expression(
                            "REPLACE(REPLACE({$columnName}, '-', ''), ' ', '') LIKE {$paramName}",
                            [$paramName => '%'.$searchWord['normalized'].'%']
                        );
                    }

                    $tmpWhere[] = $wordWhere;
                }

                $where[] = array_merge(['and'], $tmpWhere);
            }
        }

        $tmpWhere = [];
        foreach ($searchQueryArr as $searchWord) {
            foreach ($searchWord['variants'] as $value) {
                $tmpWhere[] = [
                    '=',
                    "shopProduct.brand_sku",
                    $value
                ];
            }

            if ($searchWord['normalized']) {
                $conditionIndex++;
                $paramName = ':sxSearchSkuNormalized'.$conditionIndex;
                $tmpWhere[] = new \yii\db\Expression(
                    "REPLACE(REPLACE(shopProduct.brand_sku, '-', ''), ' ', '') = {$paramName}",
                    [$paramName => $searchWord['normalized']]
                );
            }

            $where[] = array_merge(['or'], $tmpWhere);
        }

        if ($where) {
            $where = array_merge(['or'], $where);
            $activeQuery->andWhere($where);
        }

        $activeQuery->andWhere([CmsContentElement::tableName().'.parent_content_element_id' => null]);

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
