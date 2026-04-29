<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Appointment model (мої записи)
 *
 * @property int $id
 * @property int $user_id
 * @property string $specialist_name
 * @property string $specialist_type
 * @property string $appointment_date (формат: YYYY-MM-DD)
 * @property string $appointment_time (формат: HH:MM)
 * @property string $status (confirmed, pending, completed, cancelled, noshow)
 * @property string $payment_status (paid, unpaid)
 * @property string $notes
 * @property float $price
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class Appointment extends ActiveRecord
{
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_NOSHOW = 'noshow';

    const PAYMENT_PAID         = 'paid';
    const PAYMENT_UNPAID       = 'unpaid';
    const PAYMENT_PENDING      = 'pending';
    const PAYMENT_NOT_REQUIRED = 'not_required';
    const PAYMENT_FAILED       = 'failed';
    const PAYMENT_SUBSCRIPTION = 'subscription';

    public static function tableName()
    {
        return '{{%appointment}}';
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
            [['user_id', 'specialist_name', 'specialist_type', 'appointment_date', 'appointment_time'], 'required'],
            [['user_id', 'specialist_id'], 'integer'],
            [['specialist_name', 'specialist_type', 'notes'], 'string'],
            [['appointment_date'], 'date', 'format' => 'php:Y-m-d'],
            [['appointment_time'], 'match', 'pattern' => '/^\d{2}:\d{2}$/'],
            [['status'], 'in', 'range' => [self::STATUS_CONFIRMED, self::STATUS_PENDING, self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NOSHOW]],
            [['payment_status'], 'in', 'range' => [self::PAYMENT_PAID, self::PAYMENT_UNPAID, self::PAYMENT_PENDING, self::PAYMENT_NOT_REQUIRED, self::PAYMENT_FAILED, self::PAYMENT_SUBSCRIPTION]],
            [['price'], 'number', 'min' => 0],
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
            'specialist_name' => 'Імя спеціаліста',
            'specialist_type' => 'Тип спеціаліста',
            'appointment_date' => 'Дата запису',
            'appointment_time' => 'Час запису',
            'status' => 'Статус',
            'payment_status' => 'Статус оплати',
            'notes' => 'Примітки',
            'price' => 'Вартість',
        ];
    }

    public function getStatusLabel()
    {
        $labels = [
            self::STATUS_CONFIRMED => 'Підтверджено',
            self::STATUS_PENDING => 'Очікує підтвердження',
            self::STATUS_COMPLETED => 'Завершено',
            self::STATUS_CANCELLED => 'Скасовано',
            self::STATUS_NOSHOW => 'Не з\'явився',
        ];
        return $labels[$this->status] ?? $this->status;
    }
}
