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
        $row = Yii::$app->db->createCommand(
            'SELECT value FROM app_settings WHERE key = :k',
            [':k' => $key]
        )->queryScalar();

        return $row !== false ? (string)$row : $default;
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
    }

    public static function getAll(array $keys): array
    {
        if (!$keys) return [];
        $placeholders = implode(',', array_map(fn($k) => "'$k'", $keys));
        $rows = Yii::$app->db->createCommand(
            "SELECT key, value FROM app_settings WHERE key IN ($placeholders)"
        )->queryAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['key']] = $r['value'];
        }
        foreach ($keys as $k) {
            if (!isset($map[$k])) $map[$k] = '';
        }
        return $map;
    }
}
