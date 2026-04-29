<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int    $user_id
 * @property string $access_token
 * @property string $refresh_token
 * @property int    $expires_at
 * @property string $google_email
 * @property int    $created_at
 * @property int    $updated_at
 */
class UserGoogleToken extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'user_google_token';
    }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id', 'expires_at', 'created_at', 'updated_at'], 'integer'],
            [['access_token', 'refresh_token'], 'string'],
            [['google_email'], 'string', 'max' => 255],
        ];
    }

    public static function forUser(int $userId): ?self
    {
        return static::findOne(['user_id' => $userId]);
    }

    public function isExpired(): bool
    {
        return $this->expires_at < (time() + 60);
    }
}
