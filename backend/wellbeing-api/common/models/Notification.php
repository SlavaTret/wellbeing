<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $user_id
 * @property string $type
 * @property string $title
 * @property string $message
 * @property bool   $is_read
 * @property string $notification_channels
 * @property int    $related_appointment_id
 * @property array  $data
 * @property int    $created_at
 * @property int    $updated_at
 */
class Notification extends ActiveRecord
{
    // Legacy types
    const TYPE_APPOINTMENT_REMINDER = 'appointment_reminder';
    const TYPE_PAYMENT_REMINDER     = 'payment_reminder';
    const TYPE_SYSTEM               = 'system';
    const TYPE_OTHER                = 'other';

    // New typed notifications
    const TYPE_APPOINTMENT_CONFIRMED = 'appointment_confirmed';
    const TYPE_REMINDER_12H          = 'reminder_12h';
    const TYPE_REMINDER_1H           = 'reminder_1h';
    const TYPE_REFUND_PROCESSED      = 'refund_processed';
    const TYPE_REVIEW_REQUEST        = 'review_request';

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
            [['title', 'message', 'notification_channels', 'type'], 'string'],
            [['is_read'], 'boolean'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getIcon(): string
    {
        return match ($this->type) {
            self::TYPE_APPOINTMENT_CONFIRMED => 'check',
            self::TYPE_REMINDER_12H          => 'clock',
            self::TYPE_REMINDER_1H           => 'clock',
            self::TYPE_REFUND_PROCESSED      => 'card',
            self::TYPE_REVIEW_REQUEST        => 'star',
            self::TYPE_APPOINTMENT_REMINDER  => 'bell',
            self::TYPE_PAYMENT_REMINDER      => 'card',
            self::TYPE_SYSTEM                => 'info',
            default                          => 'bell',
        };
    }

    public function toClientArray(): array
    {
        // Parse data column — may be JSONB string from PostgreSQL
        $data = null;
        $raw  = $this->getAttribute('data');
        if ($raw) {
            $data = is_array($raw) ? $raw : json_decode($raw, true);
        }

        return [
            'id'                     => $this->id,
            'title'                  => $this->title,
            'body'                   => $this->message,
            'icon'                   => $this->getIcon(),
            'type'                   => $this->type,
            'is_read'                => (bool)$this->is_read,
            'time'                   => $this->timeLabel(),
            'related_appointment_id' => $this->related_appointment_id,
            'data'                   => $data,
        ];
    }

    private function timeLabel(): string
    {
        $diff = time() - (int)$this->created_at;
        if ($diff < 60)     return 'щойно';
        if ($diff < 3600)   return round($diff / 60) . ' хв тому';
        if ($diff < 86400)  return round($diff / 3600) . ' год тому';
        $days = round($diff / 86400);
        $word = $days === 1 ? 'день' : ($days < 5 ? 'дні' : 'днів');
        return "$days $word тому";
    }
}
