<?php
define('YII_ENABLE_ERROR_HANDLER', false);
$vendor = (getenv('SKEEKS_APP_ROOT') ?: '/app').'/vendor';
require $vendor.'/autoload.php';
require $vendor.'/yiisoft/yii2/Yii.php';
$app = new yii\console\Application([
    'id' => 'content-search-test', 'basePath' => __DIR__, 'vendorPath' => $vendor, 'extensions' => [],
    'components' => ['db' => ['class' => yii\db\Connection::class, 'dsn' => 'sqlite::memory:']],
]);
class SearchTestContent extends yii\db\ActiveRecord
{
    public static function tableName() { return 'cms_content_element'; }
}
class SearchTestProduct extends yii\db\ActiveRecord
{
    public static function tableName() { return 'search_test_product'; }
}
class SearchTestShopContent extends SearchTestContent
{
    public function getShopProduct() { return $this->hasOne(SearchTestProduct::class, ['id' => 'id']); }
}
class TestContentSearch extends skeeks\cms\search\CmsSearchComponent
{
    public $term;
    public function init() {}
    public function getSearchQuery() { return $this->term; }
}
$app->db->createCommand('CREATE TABLE cms_content_element (id INTEGER PRIMARY KEY, name TEXT, parent_content_element_id INTEGER)')->execute();
$app->db->createCommand('CREATE TABLE search_test_product (id INTEGER PRIMARY KEY, brand_sku TEXT)')->execute();
$app->db->createCommand()->batchInsert('cms_content_element', ['id', 'name'], [
    [1, 'api ключ'], [2, 'сайт'], [3, 'товар'],
])->execute();
$app->db->createCommand()->insert('search_test_product', ['id' => 3, 'brand_sku' => 'sku-6684'])->execute();
$search = new TestContentSearch(['enabledElementProperties' => 'N', 'searchElementFields' => ['name']]);
foreach ([SearchTestContent::class, SearchTestShopContent::class] as $modelClass) {
    foreach (['api ключ' => [1], 'сайт' => [2], 'nonexistent6684' => [], 'SKU6684' => $modelClass === SearchTestShopContent::class ? [3] : []] as $term => $expected) {
        $search->term = $term;
        $query = $modelClass::find()->groupBy('cms_content_element.id');
        $search->buildElementsQuery($query);
        $provider = new yii\data\ActiveDataProvider(['query' => $query]);
        $total = $provider->getTotalCount();
        $ids = array_map(static function ($model) { return (int)$model->id; }, $provider->getModels());
        if ($ids !== $expected || $total !== count($expected)) {
            throw new RuntimeException($modelClass.' failed for '.$term);
        }
    }
}
echo "PASS: generic and shop content, multiword query, ordinary query, empty results and normalized SKU\n";
