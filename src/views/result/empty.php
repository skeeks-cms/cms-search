<?php
use skeeks\cms\models\CmsTree;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $query string normalized search query */
\skeeks\cms\search\assets\SearchResultsAsset::register($this);
$catalog = null;
$sections = [];
$contacts = null;
$phone = null;
try {
    $site = Yii::$app->cms->cmsSite;
    $shop = $site->hasProperty('shopSite') ? $site->shopSite : null;
    $catalog = $shop ? $shop->catalogMainCmsTree : null;
    $notRedirect = ['or', ['redirect' => null], ['redirect' => '']];
    $sections = $catalog
        ? $catalog->getActiveChildren()->andWhere($notRedirect)->orderBy(['priority' => SORT_ASC])->limit(8)->all()
        : CmsTree::find()->cmsSite()->active()->andWhere(['level' => 1])->andWhere($notRedirect)->orderBy(['priority' => SORT_ASC])->limit(8)->all();
    $contacts = CmsTree::find()->cmsSite()->active()->andWhere(['level' => 1, 'code' => ['contacts', 'kontakty']])->one();
    $phone = $site->cmsSitePhone;
} catch (\Throwable $e) {
    Yii::error('Search recovery navigation: '.$e->getMessage(), __METHOD__);
}
?>
<section class="sx-search-empty" aria-labelledby="sx-search-empty-title">
    <h1 id="sx-search-empty-title"><?= $query === '' ? 'Введите название или артикул' : 'По вашему запросу ничего не найдено' ?></h1>
    <p class="sx-search-empty__text"><?= $query === '' ? 'Найдите нужный товар с помощью поиска или выберите раздел каталога.' : 'Проверьте написание или сократите запрос до названия коллекции или артикула. Можно перейти в каталог или обратиться к нам за помощью с подбором.' ?></p>
    <form class="sx-search-empty__form" action="<?= Html::encode(Url::to(['/cmsSearch/result/index'])) ?>" method="get" role="search">
        <label for="sx-search-empty-query">Артикул или название</label>
        <div class="sx-search-empty__input-row">
            <input id="sx-search-empty-query" type="search" name="<?= Html::encode(Yii::$app->cmsSearch->searchQueryParamName) ?>" value="<?= Html::encode($query) ?>" required>
            <button class="sx-search-empty__primary" type="submit">Найти</button>
        </div>
    </form>
    <div class="sx-search-empty__actions">
        <a class="sx-search-empty__primary" href="<?= Html::encode($catalog ? $catalog->url : Url::home()) ?>" data-pjax="0"><?= $catalog ? 'Перейти в каталог' : 'Вернуться на главную' ?></a>
    </div>
    <?php if ($sections): ?>
    <nav class="sx-search-empty__sections" aria-label="Разделы каталога">
        <?php foreach ($sections as $section): ?>
        <a href="<?= Html::encode($section->url) ?>" data-pjax="0"><?= Html::encode($section->name) ?></a>
        <?php endforeach ?>
    </nav>
    <?php endif ?>
    <?php if ($contacts || $phone): ?>
    <div class="sx-search-empty__contacts">
        <?php if ($contacts): ?><a href="<?= Html::encode($contacts->url) ?>" data-pjax="0"><?= Html::encode($contacts->name) ?></a><?php endif ?>
        <?php if ($contacts && $phone): ?> · <?php endif ?>
        <?php if ($phone): ?><a href="tel:<?= Html::encode(preg_replace('/[^\d+]/', '', $phone->value)) ?>"><?= Html::encode($phone->value) ?></a><?php endif ?>
    </div>
    <?php endif ?>
</section>
