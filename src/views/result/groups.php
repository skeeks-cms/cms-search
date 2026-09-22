<?php
use yii\helpers\Html;
use yii\helpers\Url;
use skeeks\cms\search\services\StorefrontSuggest;
use skeeks\cms\search\services\SuggestQuery;

\skeeks\cms\search\assets\SearchResultsAsset::register($this);
$query = SuggestQuery::normalize(Yii::$app->request->get(Yii::$app->cmsSearch->searchQueryParamName, ''));
$service = new StorefrontSuggest();
?>
<div class="sx-search-results" data-query="<?= Html::encode($query) ?>" data-endpoint="<?= Html::encode(Url::to(['/cmsSearch/suggest/page'])) ?>">
    <h1>Результаты поиска: «<?= Html::encode($query) ?>»</h1>
    <?php foreach (['sections','filters','brands','collections'] as $groupId):
        $group = $service->page($query, $groupId);
        if (!$group['items']) continue;
    ?>
    <section class="sx-search-results__group" data-group="<?= $groupId ?>" aria-labelledby="sx-results-<?= $groupId ?>">
        <h2 id="sx-results-<?= $groupId ?>"><?= Html::encode($group['title']) ?></h2>
        <div class="sx-search-results__items">
            <?php foreach ($group['items'] as $item): ?>
                <a class="sx-search-results__item" data-pjax="0" data-id="<?= $item['id'] ?>" href="<?= Html::encode($item['url']) ?>">
                    <?php if ($item['image']): ?><img src="<?= Html::encode($item['image']) ?>" alt="" loading="lazy" width="80" height="80"><?php endif ?>
                    <span><span class="sx-search-results__name"><?= Html::encode($item['name']) ?></span><span class="sx-search-results__subtitle"><?= Html::encode($item['subtitle']) ?></span></span>
                </a>
            <?php endforeach ?>
        </div>
        <button type="button" class="sx-search-results__more" data-page="<?= (int)$group['nextPage'] ?>" <?= !$group['nextPage'] ? 'hidden' : '' ?>>Показать ещё</button>
        <span class="sx-search-results__status" role="status" aria-live="polite"></span>
    </section>
    <?php endforeach ?>
</div>
