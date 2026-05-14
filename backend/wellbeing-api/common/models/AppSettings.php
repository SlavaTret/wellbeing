<?php

namespace common\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property string $key
 * @property string $value
 * @property int    $updated_at
 */
class AppSettings extends ActiveRecord
{
    private const CACHE_KEY = 'app_settings_all';
    private const CACHE_TTL = 300; // 5 хвилин

    public static function tableName(): string
    {
        return 'app_settings';
    }

    public function rules(): array
    {
        return [
            [['key'], 'required'],
            [['key', 'value'], 'string'],
        ];
    }

    public static function get(string $key, string $default = ''): string
    {
        return self::loadCached()[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Yii::$app->db->createCommand()->upsert('app_settings', [
            'key'        => $key,
            'value'      => $value,
            'updated_at' => time(),
        ], [
            'value'      => $value,
            'updated_at' => time(),
        ])->execute();

        // Скидаємо кеш — наступний запит підтягне свіжі дані
        Yii::$app->cache->delete(self::CACHE_KEY);
    }

    public static function getAll(array $keys): array
    {
        if (!$keys) return [];
        $all    = self::loadCached();
        $result = [];
        foreach ($keys as $k) {
            $result[$k] = $all[$k] ?? '';
        }
        return $result;
    }

    // ─────────────────────────────────────────────────────────────

    private static function loadCached(): array
    {
        $cached = Yii::$app->cache->get(self::CACHE_KEY);
        if ($cached !== false) {
            return $cached;
        }

        $rows = Yii::$app->db->createCommand(
            'SELECT key, value FROM app_settings'
        )->queryAll();

        $map = [];
        foreach ($rows as $r) {
            $map[$r['key']] = $r['value'];
        }

        Yii::$app->cache->set(self::CACHE_KEY, $map, self::CACHE_TTL);
        return $map;
    }
}
