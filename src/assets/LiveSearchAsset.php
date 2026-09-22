<?php
namespace skeeks\cms\search\assets;

use yii\web\AssetBundle;
use yii\helpers\Json;
use yii\helpers\Url;

class LiveSearchAsset extends AssetBundle
{
    public $sourcePath = '@skeeks/cms/search/assets/live';
    public $js = ['live-search.js'];
    public $css = ['live-search.css'];

    public static function register($view)
    {
        $asset = parent::register($view);
        $config = Json::htmlEncode([
            'endpoint' => Url::to(['/cmsSearch/suggest/index']),
            'resultsUrl' => Url::to(['/cmsSearch/result/index']),
            'queryParam' => \Yii::$app->cmsSearch->searchQueryParamName,
        ]);
        $view->registerJs("window.SkeeksLiveSearch && window.SkeeksLiveSearch.init($config);", \yii\web\View::POS_READY, 'sx-live-search');
        return $asset;
    }
}
