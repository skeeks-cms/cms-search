<?php
/** Call runSuggestQueryTests($db) with a Yii MySQL connection.
 * Only a uniquely named connection-local TEMPORARY table is written. */
use skeeks\cms\search\services\SuggestQuery;
use yii\db\Query;

function runSuggestQueryTests(\yii\db\Connection $db): void
{
    $table = 'sx_suggest_test_'.bin2hex(random_bytes(6));
    $checks = 0;
    $check = static function ($actual, $expected, $message) use (&$checks) {
        ++$checks;
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
    $check(SuggestQuery::alternatives('ПРИВИЛЕДЖ'), ['priviledzh', 'privileg', 'privilej'], 'Foreign g and j supplement literal dzh');
    $check(SuggestQuery::alternatives('джангл SKU-G12'), ['dzhangl sku-g12', 'gangl sku-g12', 'jangl sku-g12'], 'Keep mixed Latin SKU intact');
    $check(count(SuggestQuery::alternatives('джай хей джой')), 6, 'Digraph variants stay bounded across words');
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
            [20,1,1,'Привиледж','RU'], [21,1,1,'Privilege','G'],
            [22,1,1,'Priviledzh','DZH'], [23,1,1,'Privilej','J'],
            [24,2,1,'Privilege чужой','FOREIGN'], [25,1,0,'Privilege скрытый','HIDDEN'],
            [26,1,1,'Privilege / Привиледж','BOTH'], [27,1,1,'Privilege Avorio','AVORIO'],
        ])->execute();
        $digraph = (new Query())->select('id')->from($table)->where(['site'=>1,'active'=>1]);
        SuggestQuery::applyAll($digraph, 'привиледж', ['name','sku'], 'name');
        $digraph->addOrderBy(['id'=>SORT_ASC]);
        $check(array_map('intval', (clone $digraph)->column($db)), [20,26,22,23,21,27], 'Original first, all digraph spellings, no duplicates, preserve scope');
        $check(array_map('intval', (clone $digraph)->offset(2)->limit(2)->column($db)), [22,23], 'Digraph pagination stays stable');
        $suggestIds = [];
        foreach (array_merge(['привиледж'], SuggestQuery::alternatives('привиледж')) as $spelling) {
            $suggestIds = array_values(array_unique(array_merge($suggestIds, $find($spelling))));
        }
        $check($suggestIds, [20,26,22,21,27,23], 'Suggestion passes retain original and literal matches');
        $multi = (new Query())->select('id')->from($table)->where(['site'=>1,'active'=>1]);
        SuggestQuery::applyAll($multi, 'привиледж аворио', ['name','sku'], 'name');
        $check(array_map('intval', $multi->column($db)), [27], 'Digraph matching preserves multi-word AND');
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
        echo "SuggestQuery: $checks checks passed\n";
    } finally {
        $db->createCommand("DROP TEMPORARY TABLE `$table`")->execute();
    }
}
