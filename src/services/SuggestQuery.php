<?php
namespace skeeks\cms\search\services;

use yii\db\Expression;

/** Bounded, literal multi-word matching shared by storefront suggest providers. */
final class SuggestQuery
{
    public static function normalize($value): string
    {
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value)), 0, 120);
    }

    public static function words(string $value): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($value), $matches);
        return array_slice($matches[0], 0, 5);
    }

    /** Two bounded Russian-to-Latin spellings, independent of the intl extension. */
    public static function alternatives(string $text): array
    {
        $text = mb_strtolower($text);
        if (!preg_match('/[а-яё]/u', $text)) {
            return [];
        }
        $map = array_combine(
            preg_split('//u', 'абвгдеёжзийклмнопрстуфхцчшщъыьэюя', -1, PREG_SPLIT_NO_EMPTY),
            ['a','b','v','g','d','e','e','zh','z','i','y','k','l','m','n','o','p','r','s','t','u','f','h','ts','ch','sh','sch','','y','','e','yu','ya']
        );
        $variants = [strtr($text, $map), strtr($text, array_replace($map, ['й'=>'i', 'х'=>'kh', 'ц'=>'c', 'щ'=>'shch', 'ю'=>'iu', 'я'=>'ia']))];
        return array_values(array_filter(array_unique($variants), static function ($value) use ($text) {
            return $value !== $text && mb_strlen($value) >= 2 && self::words($value);
        }));
    }

    /** SQL union before pagination: one row per entity across all spellings. */
    public static function applyAll($query, string $text, array $columns, string $titleColumn): void
    {
        $conditions = ['or'];
        $original = null;
        foreach (array_merge([$text], self::alternatives($text)) as $variant) {
            $match = new \yii\db\Query();
            self::apply($match, $variant, $columns, $titleColumn);
            $conditions[] = $match->where;
            $original = $original ?: $match;
        }
        $query->andWhere($conditions);
        $params = [];
        $sql = \Yii::$app->db->queryBuilder->buildCondition($original->where, $params);
        $names = [];
        $rankParams = [];
        foreach ($params as $name => $value) {
            $names[$name] = ':sxRank'.count($names);
            $rankParams[$names[$name]] = $value;
        }
        $query->orderBy(new Expression('CASE WHEN '.strtr($sql, $names).' THEN 0 ELSE 1 END', $rankParams));
        $exact = $prefix = $titleParams = [];
        foreach (array_merge([$text], self::alternatives($text)) as $index => $variant) {
            $exact[] = "LOWER($titleColumn) = :sxTitle$index";
            $prefix[] = "LOWER($titleColumn) LIKE :sxPrefix$index";
            $titleParams[":sxTitle$index"] = mb_strtolower($variant);
            $titleParams[":sxPrefix$index"] = strtr(mb_strtolower($variant), ['\\'=>'\\\\', '%'=>'\\%', '_'=>'\\_']).'%';
        }
        $query->addOrderBy(new Expression('CASE WHEN '.implode(' OR ', $exact).' THEN 0 WHEN '.implode(' OR ', $prefix).' THEN 1 ELSE 2 END', $titleParams));
    }

    public static function apply($query, string $text, array $columns, string $titleColumn): void
    {
        $words = self::words($text);
        if (!$words) {
            $query->andWhere('0=1');
            return;
        }
        foreach ($words as $word) {
            $or = ['or'];
            foreach ($columns as $column) {
                $or[] = ['like', $column, $word];
                foreach (array_unique([strtr($word, ['х' => 'x', 'ё' => 'е']), strtr($word, ['x' => 'х', 'ё' => 'е'])]) as $alternate) {
                    if ($alternate !== $word) {
                        $or[] = ['like', $column, $alternate];
                    }
                }
            }
            $query->andWhere($or);
        }
        $prefix = strtr(mb_strtolower($text), ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']).'%';
        $query->orderBy(new Expression(
            "CASE WHEN LOWER({$titleColumn}) = :sxExact THEN 0 WHEN LOWER({$titleColumn}) LIKE :sxPrefix THEN 1 ELSE 2 END",
            [':sxExact' => mb_strtolower($text), ':sxPrefix' => $prefix]
        ));
    }
}
