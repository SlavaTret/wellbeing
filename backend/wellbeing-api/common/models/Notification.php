<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $user_id
 * @property string $type  (appointment_reminder | payment_reminder | system | other)
 * @property string $title
 * @property string $message
 * @property bool   $is_read
 * @property string $notification_channels
 * @property int    $related_appointment_id
 * @property int    $created_at
 * @property int    $updated_at
 */
class Notification extends ActiveRecord
{
    const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';
    const TYPE_PAYMENT_REMINDER     = 'payment_reminder';
    const TYPE_SYSTEM               = 'system';
    const TYPE_OTHER                = 'other';

    public static function tableName(): string { return '{{%notification}}'; }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['user_id', 'type', 'title', 'message'], 'required'],
            [['user_id', 'related_appointment_id', 'created_at', 'updated_at'], 'integer'],
            [['title', 'message', 'notification_channels'], 'string'],
            [['type'], 'in', 'range' => [self::TYPE_APPOINTMENT_REMINDER, self::TYPE_PAYMENT_REMINDER, self::TYPE_SYSTEM, self::TYPE_OTHER]],
            [['is_read'], 'boolean'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    /** Icon name derived from type */
    public function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_APPOINTMENT_REMINDER => 'bell',
            self::TYPE_PAYMENT_REMINDER     => 'card',
            self::TYPE_SYSTEM               => 'info',
            default                         => 'check',
        };
    }

    public function toClientArray(): array
    {
        return [
            'id'       => $this->id,
            'title'    => $this->title,
            'body'     => $this->message,
            'icon'     => $this->getIcon(),
            'type'     => $this->type,
            'is_read'  => (bool)$this->is_read,
            'time'     => $this->timeLabel(),
        ];
    }

    private function timeLabel(): string
    {
        $diff = time() - (int)$this->created_at;
        if ($diff < 3600)   return round($diff / 60) . ' хв тому';
        if ($diff < 86400)  return round($diff / 3600) . ' год тому';
        $days = round($diff / 86400);
        $word = $days === 1 ? 'день' : ($days < 5 ? 'дні' : 'днів');
        return "$days $word тому";
    }
}
