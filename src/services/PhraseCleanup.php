<?php
namespace skeeks\cms\search\services;

use skeeks\cms\search\models\CmsSearchPhrase;
use yii\base\BaseObject;
use yii\db\Query;

/** Bounded deletes with a fixed cutoff; shared by the native job and CLI. */
class PhraseCleanup extends BaseObject
{
    public $batchSize = 1000;

    public function run(int $lifetime, ?callable $checkpoint = null): array
    {
        if ($this->batchSize < 1) { throw new \InvalidArgumentException('batchSize must be positive.'); }
        $result = ['enabled' => (bool)$lifetime, 'cutoff' => time() - $lifetime, 'deleted' => 0, 'batches' => 0];
        if ($checkpoint) { $checkpoint($result); }
        if (!$result['enabled']) { return $result; }

        $table = CmsSearchPhrase::tableName();
        $condition = ['<=', 'created_at', $result['cutoff']];
        // New rows cannot make this execution unbounded.
        $upper = (new Query())->from($table)->where($condition)->max('id');
        $lastId = 0;
        while ($upper !== null) {
            if ($checkpoint) { $checkpoint($result); }
            $ids = (new Query())->select('id')->from($table)->where($condition)
                ->andWhere(['>', 'id', $lastId])->andWhere(['<=', 'id', $upper])
                ->orderBy(['id' => SORT_ASC])->limit((int)$this->batchSize)->column();
            if (!$ids) { break; }
            // Recheck age: another process may have refreshed a selected record.
            $result['deleted'] += CmsSearchPhrase::deleteAll(['and', ['id' => $ids], $condition]);
            ++$result['batches'];
            $lastId = end($ids);
            if ($checkpoint) { $checkpoint($result); }
        }
        return $result;
    }
}