<?php
/** Call runSuggestQueryTests($db) with a Yii MySQL connection.
 * Only a uniquely named connection-local TEMPORARY table is written. */
use skeeks\cms\search\services\SuggestQuery;
use yii\db\Query;

function runSuggestQueryTests(\yii\db\Connection $db): void
{
    $table = 'sx_suggest_test_'.bin2hex(random_bytes(6));
    $check = static function ($actual, $expected, $message) {
        if ($actual !== $expected) {
            throw new RuntimeException($message.': '.json_encode($actual, JSON_UNESCAPED_UNICODE));
        }
    };
    $check(SuggestQuery::normalize(['q' => 'bad']), '', 'Reject arrays');
    $check(SuggestQuery::normalize("  Slim\n  белый  "), 'Slim белый', 'Collapse whitespace');
    $check(mb_strlen(SuggestQuery::normalize(str_repeat('а', 160))), 120, 'Bound text');
    $check(count(SuggestQuery::words('а б в г д е ж')), 5, 'Bound words');
    $check(SuggestQuery::alternatives('Естима'), ['estima'], 'Russian brand transliteration');
    $check(SuggestQuery::alternatives('Estima SF01'), [], 'Do not change Latin names and SKUs');
    $check(SuggestQuery::alternatives('естима SF01'), ['estima sf01'], 'Preserve mixed SKU');
    $check(SuggestQuery::alternatives('хайтек'), ['haytek', 'khaitek'], 'Bound alternative spellings');
    $db->createCommand("CREATE TEMPORARY TABLE `$table` (id INT, site INT, active INT, name VARCHAR(255), sku VARCHAR(255)) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")->execute();
    try {
        $db->createCommand()->batchInsert($table, ['id','site','active','name','sku'], [
            [1,1,1,'Slim','ABC-120'], [2,1,1,'Slim белый 60х60','DEF-121'],
            [3,1,1,'Slim серый 60x60','DEF-122'], [4,2,1,'Slim чужой','ABC-120'],
            [5,1,0,'Slim скрытый','ABC-120'], [6,1,1,'Белый Slim','ZZ-9'],
            [7,1,1,'Атлас / Atlas','AT-1'], [8,1,1,'Atlas','AT-2'],
            [9,1,1,'Atlas Concorde','AT-3'], [10,2,1,'Atlas чужой','AT-4'],
        ])->execute();
        $find = static function ($word) use ($db, $table) {
            $query = (new Query())->select('id')->from($table)->where(['site'=>1,'active'=>1]);
            SuggestQuery::apply($query, SuggestQuery::normalize($word), ['name','sku'], 'name');
            return array_map('intval', $query->addOrderBy(['id'=>SORT_ASC])->column($db));
        };
        $check($find('Slim'), [1,2,3,6], 'Exact name before prefix; preserve visibility scope');
        $check($find('Slim ABC-120'), [1], 'AND words across name and SKU');
        $check($find('60x60'), [2,3], 'Latin dimension finds both alphabets');
        $check($find('60х60'), [2,3], 'Cyrillic dimension finds both alphabets');
        $check($find('%_'), [], 'Punctuation must not match entire catalog');
        $check($find("' OR 1=1 --"), [], 'SQL-looking input stays literal');
        $check($find(''), [], 'Empty query is bounded');
        $all = (new Query())->select('id')->from($table)->where(['site'=>1,'active'=>1]);
        SuggestQuery::applyAll($all, 'атлас', ['name','sku'], 'name');
        $all->addOrderBy(['id'=>SORT_ASC]);
        $check(array_map('intval', (clone $all)->column($db)), [7,8,9], 'Merge spellings before pagination without duplicates');
        $check(array_map('intval', (clone $all)->limit(2)->column($db)), [7,8], 'First page keeps original first');
        $check(array_map('intval', (clone $all)->offset(2)->limit(2)->column($db)), [9], 'Next page has no skips or overlap');
        $db->createCommand()->batchInsert($table, ['id','site','active','name','sku'], [
            [7045802,1,1,'Belleza Даф бежевая','00-00-5-17'],
            [7045803,1,1,'7045802','7045802'], [17045802,1,1,'Другой товар','OTHER'],
            [7045804,1,0,'Скрытый товар','HIDDEN'], [7045805,2,1,'Другой сайт','FOREIGN'],
        ])->execute();
        $findProduct = static function ($text, $allSpellings) use ($db, $table) {
            $query = (new Query())->select('id')->from($table)->where(['site'=>1,'active'=>1]);
            SuggestQuery::applyProduct($query, $text, ['name','sku'], 'name', 'id', $allSpellings);
            return array_map('intval', $query->addOrderBy(['id'=>SORT_ASC])->column($db));
        };
        foreach ([false, true] as $allSpellings) {
            $check($findProduct('7045802', $allSpellings), [7045802,7045803], 'Exact ID first; preserve text/SKU matches; no partial IDs');
            $check($findProduct('7045804', $allSpellings), [], 'ID cannot expose inactive products');
            $check($findProduct('7045805', $allSpellings), [], 'ID cannot expose another site');
            $check($findProduct('9999999', $allSpellings), [], 'Missing ID');
            $check($findProduct('Slim', $allSpellings), [1,2,3,6], 'Keep text matching');
        }
        $check(SuggestQuery::isProductId('1'), true, 'Single digit ID');
        $check(SuggestQuery::isProductId('7045802abc'), false, 'Reject mixed ID');
        $check(SuggestQuery::isProductId('999999999999999999999'), false, 'Bound numeric ID');
        echo "SuggestQuery: 31 checks passed\n";
    } finally {
        $db->createCommand("DROP TEMPORARY TABLE `$table`")->execute();
    }
}
