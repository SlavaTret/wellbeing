<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int  $id
 * @property int  $user_id
 * @property bool $email_enabled
 * @property bool $calendar_enabled
 * @property bool $sms_enabled
 * @property bool $reminders_enabled
 * @property int  $created_at
 * @property int  $updated_at
 */
class NotificationSetting extends ActiveRecord
{
    public static function tableName(): string { return '{{%notification_setting}}'; }

    public function behaviors(): array { return [TimestampBehavior::class]; }

    public function rules(): array
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
            [['email_enabled', 'calendar_enabled', 'sms_enabled', 'reminders_enabled'], 'boolean'],
        ];
    }

    public static function forUser(int $userId): self
    {
        $setting = static::findOne(['user_id' => $userId]);
        if (!$setting) {
            $setting = new static([
                'user_id'           => $userId,
                'email_enabled'     => true,
                'calendar_enabled'  => true,
                'sms_enabled'       => false,
                'reminders_enabled' => true,
            ]);
            $setting->save(false);
        }
        return $setting;
    }
}
