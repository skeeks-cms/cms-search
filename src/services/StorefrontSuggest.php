<?php
namespace skeeks\cms\search\services;

use skeeks\cms\models\CmsTree;
use skeeks\cms\models\CmsSavedFilter;
use skeeks\cms\models\CmsContentElement;
use skeeks\cms\models\CmsContentPropertyEnum;
use skeeks\cms\models\CmsCountry;
use skeeks\cms\shop\models\ShopBrand;
use skeeks\cms\shop\models\ShopCollection;
use skeeks\cms\shop\models\ShopCmsContentElement;
use skeeks\cms\shop\models\ShopProduct;
use skeeks\cms\shop\models\ShopUser;
use skeeks\cms\shop\models\ShopOrder;
use yii\db\Query;

/** Read-only suggestions. No total counts, search-log writes or shared price cache. */
class StorefrontSuggest
{
    private $priceOrder;
    public $matchedQuery = '';
    private $pageSize;
    private $pageOffset = 0;

    /** Paginated navigation groups; product cards use productQuery() in the shop theme. */
    public function page(string $text, string $group, int $page = 1): array
    {
        $titles = ['sections'=>'Разделы', 'filters'=>'Подборки', 'brands'=>'Бренды', 'collections'=>'Коллекции'];
        if (!isset($titles[$group])) {
            throw new \InvalidArgumentException('Unknown search group');
        }
        $this->pageSize = 8;
        $this->pageOffset = (max(1, $page) - 1) * $this->pageSize;
        $items = [];
        try {
            if (mb_strlen($text) >= 2 && SuggestQuery::words($text)
                && (!in_array($group, ['brands','collections'], true) || (class_exists(ShopCollection::class) && \Yii::$app->has('shop')))) {
                $items = $this->$group($text);
            }
            return ['id'=>$group, 'title'=>$titles[$group], 'items'=>array_slice($items, 0, $this->pageSize),
                'nextPage'=>count($items) > $this->pageSize ? $page + 1 : null];
        } finally {
            $this->pageSize = null;
            $this->pageOffset = 0;
        }
    }

    private function match($query, string $text, array $columns, string $title): void
    {
        if ($this->pageSize) {
            SuggestQuery::applyAll($query, $text, $columns, $title);
        } else {
            SuggestQuery::apply($query, $text, $columns, $title);
        }
    }

    public function search(string $text): array
    {
        $this->matchedQuery = $text;
        if (mb_strlen($text) < 2 || !SuggestQuery::words($text)) {
            return [];
        }
        $hasShop = class_exists(ShopCollection::class) && \Yii::$app->has('shop');
        if ($hasShop) {
            $this->preparePriceOrder();
        }
        $groups = $this->searchGroups($text, $hasShop);
        $merged = array_column($groups, null, 'id');
        foreach (SuggestQuery::alternatives($text) as $alternate) {
            $additional = $this->searchGroups($alternate, $hasShop);
            if (!$merged && $additional) {
                $this->matchedQuery = $alternate;
            }
            foreach ($additional as $group) {
                $id = $group['id'];
                if (!isset($merged[$id])) {
                    $merged[$id] = $group;
                    continue;
                }
                $items = array_column($merged[$id]['items'], null, 'id');
                foreach ($group['items'] as $item) {
                    if (!isset($items[$item['id']])) {
                        $items[$item['id']] = $item;
                    }
                }
                $merged[$id]['items'] = array_slice(array_values($items), 0, $id === 'products' ? 6 : 4);
            }
        }
        // Original matches retain priority; each group stays bounded and appears once.
        $groups = [];
        foreach (['sections', 'filters', 'brands', 'collections', 'products'] as $id) {
            if (isset($merged[$id])) {
                $groups[] = $merged[$id];
            }
        }
        return $groups;
    }

    private function searchGroups(string $text, bool $hasShop): array
    {
        $groups = [];
        $this->append($groups, 'sections', 'Разделы', $this->sections($text));
        $this->append($groups, 'filters', 'Подборки', $this->filters($text));
        if ($hasShop) {
            $this->append($groups, 'brands', 'Бренды', $this->brands($text));
            $this->append($groups, 'collections', 'Коллекции', $this->collections($text));
            $this->append($groups, 'products', 'Товары', $this->products($text));
        }
        return $groups;
    }

    /** Read price/coupon context without the shopUser getter, which can merge/save carts. */
    private function preparePriceOrder(): void
    {
        $userId = \Yii::$app->user->id;
        $session = \Yii::$app->session;
        $guestId = null;
        if (!$userId && ($session->hasSessionId || $session->isActive)) {
            $guestId = $session->get(\Yii::$app->shop->sessionFuserName);
        }
        if ($session->isActive) {
            $session->close();
        }
        $siteId = \Yii::$app->skeeks->site->id;
        $shopUser = null;
        if ($userId) {
            $shopUser = ShopUser::find()->cmsSite()->andWhere(['cms_user_id' => $userId])->one();
        } elseif ($guestId) {
            $shopUser = ShopUser::find()->cmsSite()->andWhere(['id' => $guestId, 'cms_user_id' => null])->one();
        }
        $this->priceOrder = $shopUser && $shopUser->shop_order_id
            ? ShopOrder::find()->cmsSite()->andWhere(['id' => $shopUser->shop_order_id])->one() : null;
        if (!$this->priceOrder) {
            $this->priceOrder = new ShopOrder(['cms_site_id' => $siteId, 'cms_user_id' => $userId]);
        }
    }

    private function append(array &$groups, string $id, string $title, array $items): void
    {
        if ($items) {
            $groups[] = compact('id', 'title', 'items');
        }
    }

    protected function sections(string $text): array
    {
        $query = CmsTree::find()->cmsSite()->active()->with(['image', 'parent']);
        $table = CmsTree::tableName();
        $this->match($query, $text, ["$table.name", "$table.seo_h1"], "$table.name");
        $items = [];
        foreach ($query->addOrderBy(["$table.priority" => SORT_ASC, "$table.id" => SORT_ASC])->limit($this->pageSize ? $this->pageSize + 1 : 4)->offset($this->pageOffset)->all() as $model) {
            $items[] = $this->item($model, $model->name, $model->parent ? $model->parent->name : 'Каталог');
        }
        return $items;
    }

    protected function filters(string $text): array
    {
        if (!class_exists(CmsSavedFilter::class)) {
            return [];
        }
        $query = CmsSavedFilter::find()->alias('sf')
            ->innerJoin(['st' => CmsTree::tableName()], 'st.id = sf.cms_tree_id')
            ->leftJoin(['ev' => CmsContentPropertyEnum::tableName()], 'ev.id = sf.value_content_property_enum_id')
            ->leftJoin(['ce' => CmsContentElement::tableName()], 'ce.id = sf.value_content_element_id')
            ->leftJoin(['co' => CmsCountry::tableName()], 'co.alpha2 = sf.country_alpha2')
            ->andWhere(['sf.cms_site_id' => \Yii::$app->skeeks->site->id, 'st.cms_site_id' => \Yii::$app->skeeks->site->id, 'st.active' => 'Y'])
            ->with(['cmsTree.image', 'cmsImage', 'valueContentElement', 'valueContentPropertyEnum', 'country']);
        $columns = ['sf.short_name', 'sf.seo_h1', 'st.name', 'st.seo_h1', 'ev.value', 'ev.value_for_saved_filter', 'ce.name', 'co.name'];
        if (class_exists(ShopBrand::class)) {
            $query->leftJoin(['br' => ShopBrand::tableName()], 'br.id = sf.shop_brand_id')->with('brand');
            $columns[] = 'br.name';
        }
        $this->match($query, $text, $columns, 'sf.short_name');
        $items = [];
        foreach ($query->addOrderBy(['sf.priority' => SORT_ASC, 'sf.id' => SORT_ASC])->limit($this->pageSize ? $this->pageSize + 1 : 4)->offset($this->pageOffset)->all() as $model) {
            $items[] = $this->item($model, $model->seoName, $model->cmsTree->name);
        }
        return $items;
    }

    protected function brands(string $text): array
    {
        $publicProduct = (new Query())->select('sp.id')->from(['sp' => ShopProduct::tableName()])
            ->innerJoin(['pe' => CmsContentElement::tableName()], 'pe.id = sp.id')
            ->where('sp.brand_id = br.id')
            ->andWhere(['pe.cms_site_id' => \Yii::$app->skeeks->site->id, 'pe.active' => 'Y']);
        $query = ShopBrand::find()->alias('br')->andWhere(['br.is_active' => 1])
            ->andWhere(['exists', $publicProduct])->with(['logo', 'country']);
        $this->match($query, $text, ['br.name', 'br.seo_h1'], 'br.name');
        $items = [];
        foreach ($query->addOrderBy(['br.priority' => SORT_ASC, 'br.id' => SORT_ASC])->limit($this->pageSize ? $this->pageSize + 1 : 4)->offset($this->pageOffset)->all() as $model) {
            $items[] = $this->item($model, $model->name, $model->country ? $model->country->name : 'Бренд');
        }
        return $items;
    }

    protected function collections(string $text): array
    {
        $query = ShopCollection::find()->alias('sc')->andWhere(['sc.is_active' => 1])
            ->leftJoin(['br' => ShopBrand::tableName()], 'br.id = sc.shop_brand_id')
            ->with(['image', 'brand']);
        // Collections are installation-wide; expose only those with a public product on this site.
        $publicProduct = (new Query())->select('pc.shop_product_id')->from(['pc' => '{{%shop_product2collection}}'])
            ->innerJoin(['pe' => CmsContentElement::tableName()], 'pe.id = pc.shop_product_id')
            ->where('pc.shop_collection_id = sc.id')
            ->andWhere(['pe.cms_site_id' => \Yii::$app->skeeks->site->id, 'pe.active' => 'Y']);
        $query->andWhere(['exists', $publicProduct]);
        $this->match($query, $text, ['sc.name', 'sc.seo_h1', 'br.name'], 'sc.name');
        $items = [];
        foreach ($query->addOrderBy(['sc.priority' => SORT_ASC, 'sc.id' => SORT_ASC])->limit($this->pageSize ? $this->pageSize + 1 : 4)->offset($this->pageOffset)->all() as $model) {
            $items[] = $this->item($model, $model->name, $model->brand ? $model->brand->name : 'Коллекция');
        }
        return $items;
    }

    protected function products(string $text): array
    {
        $query = $this->productQuery($text, false);
        $table = CmsContentElement::tableName();
        $items = [];
        foreach ($query->addOrderBy(["$table.priority" => SORT_ASC, "$table.id" => SORT_ASC])->limit(6)->all() as $model) {
            if (!$model->shopProduct) {
                continue;
            }
            $product = $model->shopProduct;
            $item = $this->item($model, $model->name, $product->brand_sku ? 'Арт. '.$product->brand_sku : '');
            $site = \Yii::$app->skeeks->site->shopSite;
            $showPrice = $site && $site->is_show_prices;
            if ($showPrice && $site->is_show_prices_only_quantity) {
                $stocks = $product->getShopStoreProducts(\Yii::$app->shop->allStores)->all();
                $showPrice = !$stocks || array_sum(array_map(static function ($stock) { return $stock->quantity; }, $stocks)) > 0;
            }
            if ($showPrice) {
                $price = $this->priceOrder->getProductPriceHelper($model);
                if ($price && (float) $price->minMoney->getAmount() > 0) {
                    $item['price'] = ($product->isOffersProduct ? 'от ' : '').(string) $price->minMoney;
                    if ($product->measure) {
                        $item['price'] .= ' / '.$product->measure->symbol;
                    }
                }
            }
            $items[] = $item;
        }
        return $items;
    }

    public function productQuery(string $text, bool $allSpellings = true)
    {
        $query = ShopCmsContentElement::find()->cmsSite()->active()->joinWith('shopProduct', false)
            ->leftJoin(['productBrand' => ShopBrand::tableName()], 'productBrand.id = shopProduct.brand_id')
            ->with(['image', 'shopProduct.baseProductPrice', 'shopProduct.shopProductPrices', 'shopProduct.measure']);
        \Yii::$app->shop->filterByTypeContentElementQuery($query);
        $table = CmsContentElement::tableName();
        $query->andWhere(["$table.parent_content_element_id" => null]);
        if (\Yii::$app->cmsSearch->searchElementContentIds) {
            $query->andWhere(["$table.content_id" => (array) \Yii::$app->cmsSearch->searchElementContentIds]);
        }
        $matcher = $allSpellings ? 'applyAll' : 'apply';
        SuggestQuery::$matcher($query, $text, ["$table.name", "$table.external_id", 'shopProduct.brand_sku', 'productBrand.name'], "$table.name");
        if (mb_strlen($text) < 2) {
            $query->andWhere('0=1');
        }
        return $query->addOrderBy(["$table.priority" => SORT_ASC, "$table.id" => SORT_ASC]);
    }

    protected function item($model, string $name, string $subtitle): array
    {
        $image = $model instanceof ShopBrand ? $model->logo : $model->image;
        $src = null;
        if ($image) {
            $preview = \Yii::$app->imaging->getPreview($image,
                new \skeeks\cms\components\imaging\filters\Thumbnail(['w' => 112, 'h' => 112]));
            $src = $preview->src;
        }
        return ['id' => (int) $model->id, 'name' => $name, 'url' => $model->url, 'subtitle' => $subtitle, 'image' => $src];
    }
}
