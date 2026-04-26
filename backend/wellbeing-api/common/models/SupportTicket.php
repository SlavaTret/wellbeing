<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * SupportTicket model (зв'язок з WO)
 *
 * @property int $id
 * @property int $user_id
 * @property string $title
 * @property string $message
 * @property string $priority (low, medium, high, critical)
 * @property string $status (open, in_progress, resolved, closed)
 * @property string $contact_method (telegram, viber, email, phone)
 * @property string $response_message
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class SupportTicket extends ActiveRecord
{
    const STATUS_OPEN = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    const CONTACT_TELEGRAM = 'telegram';
    const CONTACT_VIBER = 'viber';
    const CONTACT_EMAIL = 'email';
    const CONTACT_PHONE = 'phone';

    public static function tableName()
    {
        return '{{%support_ticket}}';
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
            [['user_id', 'title', 'message', 'contact_method'], 'required'],
            [['user_id'], 'integer'],
            [['title', 'message', 'response_message', 'contact_method'], 'string'],
            [['priority'], 'in', 'range' => [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]],
            [['status'], 'in', 'range' => [self::STATUS_OPEN, self::STATUS_IN_PROGRESS, self::STATUS_RESOLVED, self::STATUS_CLOSED]],
            [['contact_method'], 'in', 'range' => [self::CONTACT_TELEGRAM, self::CONTACT_VIBER, self::CONTACT_EMAIL, self::CONTACT_PHONE]],
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
            'title' => 'Заголовок',
            'message' => 'Повідомлення',
            'priority' => 'Пріоритет',
            'status' => 'Статус',
            'contact_method' => 'Метод контакту',
            'response_message' => 'Відповідь',
        ];
    }

    public function getPriorityLabel()
    {
        $labels = [
            self::PRIORITY_LOW => 'Низький',
            self::PRIORITY_MEDIUM => 'Середній',
            self::PRIORITY_HIGH => 'Високий',
            self::PRIORITY_CRITICAL => 'Критичний',
        ];
        return $labels[$this->priority] ?? $this->priority;
    }

    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_OPEN => 'Відкрито',
            self::STATUS_IN_PROGRESS => 'В процесі',
            self::STATUS_RESOLVED => 'Вирішено',
            self::STATUS_CLOSED => 'Закрито',
        ];
        return $labels[$this->status] ?? $this->status;
    }
}
