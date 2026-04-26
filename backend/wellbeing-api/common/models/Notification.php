<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Notification model (сповіщення)
 *
 * @property int $id
 * @property int $user_id
 * @property string $type (appointment_reminder, payment_reminder, system, other)
 * @property string $title
 * @property string $message
 * @property boolean $is_read
 * @property string $notification_channels (email, sms, push)
 * @property string $related_appointment_id
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class Notification extends ActiveRecord
{
    const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';
    const TYPE_PAYMENT_REMINDER = 'payment_reminder';
    const TYPE_SYSTEM = 'system';
    const TYPE_OTHER = 'other';

    public static function tableName()
    {
        return '{{%notification}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['user_id', 'type', 'title', 'message'], 'required'],
            [['user_id', 'related_appointment_id'], 'integer'],
            [['title', 'message', 'notification_channels'], 'string'],
            [['type'], 'in', 'range' => [self::TYPE_APPOINTMENT_REMINDER, self::TYPE_PAYMENT_REMINDER, self::TYPE_SYSTEM, self::TYPE_OTHER]],
            [['is_read'], 'boolean'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Користувач',
            'type' => 'Тип',
            'title' => 'Заголовок',
            'message' => 'Повідомлення',
            'is_read' => 'Прочитано',
            'notification_channels' => 'Канали',
            'related_appointment_id' => 'ID запису',
        ];
    }
}
