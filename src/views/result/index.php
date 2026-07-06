<?
/**
 * @author Semenov Alexander <semenov@skeeks.com>
 * @link http://skeeks.com/
 * @copyright 2010 SkeekS (СкикС)
 * @date 06.03.2015
 */
/* @var $this \yii\web\View */

$this->registerMetaTag([
    'name' => 'robots',
    'content' => 'noindex, nofollow',
], 'robots');
\Yii::$app->response->headers->set('X-Robots-Tag', 'noindex, nofollow');
?>

<? /*= $this->render('@template/include/breadcrumbs', [
    'title' => "Результаты поиска: " . \Yii::$app->cmsSearch->searchQuery
])*/ ?>
<? \skeeks\cms\modules\admin\widgets\Pjax::begin(); ?>

    <div class="container">
        <form action="<?= \yii\helpers\Url::to(['/cmsSearch/result/index']); ?>" method="get" data-pjax="true">
            <div class="input-group animated fadeInDown">
                <input type="text" name="<?= \Yii::$app->cmsSearch->searchQueryParamName; ?>" class="form-control"
                       placeholder="<?= \Yii::t('skeeks/search', 'Searching') ?>"
                       value="<?= \Yii::$app->cmsSearch->searchQuery; ?>">
                <span class="input-group-btn">
                    <button class="btn btn-primary" type="button"
                            onclick="$('.search-open form').submit(); return false;"><?= \Yii::t('skeeks/search',
                            'Search') ?></button>
                </span>
            </div>
        </form>
    </div>

    <!--=== Content Part ===-->
    <div class="container content">
        <div class="row magazine-page">
            <div class="col-md-12">

                <?
                $pjax = \skeeks\cms\widgets\PjaxLazyLoad::begin([
                    'id' => 'sx-search-result-lazy',
                ]);
                ?>

                    <?php if ($pjax->isPjax) : ?>
                        <?= \skeeks\cms\cmsWidgets\contentElements\ContentElementsCmsWidget::widget([
                            'namespace' => 'ContentElementsCmsWidget-search-result',
                            'viewFile' => '@skeeks/cms/search/views/result/_widget',
                            'enabledCurrentTree' => \skeeks\cms\components\Cms::BOOL_N,
                            'dataProviderCallback' => function (\yii\data\ActiveDataProvider $dataProvider) {
                                \Yii::$app->cmsSearch->buildElementsQuery($dataProvider->query);
                                //\Yii::$app->cmsSearch->logResult($dataProvider);
                            },
                        ]) ?>
                    <?php else : ?>
                        <div class="sx-search-lazy-placeholder">
                            <div class="sx-search-lazy-placeholder__spinner"></div>
                            <div class="sx-search-lazy-placeholder__text"><?= \Yii::t('skeeks/search', 'Searching') ?>...</div>
                        </div>
                        <style>
                            .sx-search-lazy-placeholder {
                                min-height: 260px;
                                display: flex;
                                align-items: center;
                                justify-content: center;
                                flex-direction: column;
                                color: #555;
                                text-align: center;
                            }
                            .sx-search-lazy-placeholder__spinner {
                                width: 46px;
                                height: 46px;
                                margin-bottom: 18px;
                                border: 4px solid rgba(142, 18, 181, 0.16);
                                border-top-color: #8e12b5;
                                border-radius: 50%;
                                animation: sx-search-lazy-spin 0.8s linear infinite;
                            }
                            .sx-search-lazy-placeholder__text {
                                font-size: 28px;
                                font-weight: 600;
                                line-height: 1.25;
                            }
                            @keyframes sx-search-lazy-spin {
                                to {
                                    transform: rotate(360deg);
                                }
                            }
                        </style>
                    <?php endif; ?>

                <? $pjax::end(); ?>

            </div>
        </div>
    </div>

<? \skeeks\cms\modules\admin\widgets\Pjax::end(); ?>
