<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Payment model (оплата)
 *
 * @property int $id
 * @property int $user_id
 * @property int $appointment_id
 * @property float $amount
 * @property string $currency (UAH, USD, EUR)
 * @property string $status (pending, completed, failed, refunded)
 * @property string $payment_method (card, ua_pay, bank_transfer)
 * @property string $transaction_id
 * @property string $notes
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 * @property Appointment $appointment
 */
class Payment extends ActiveRecord
{
    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';

    const PAYMENT_METHOD_CARD = 'card';
    const PAYMENT_METHOD_UA_PAY = 'ua_pay';
    const PAYMENT_METHOD_BANK = 'bank_transfer';

    public static function tableName()
    {
        return '{{%payment}}';
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
            [['user_id', 'amount'], 'required'],
            [['user_id', 'appointment_id'], 'integer'],
            [['amount'], 'number', 'min' => 0.01],
            [['currency'], 'in', 'range' => ['UAH', 'USD', 'EUR']],
            [['status'], 'in', 'range' => [self::STATUS_PENDING, self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REFUNDED]],
            [['payment_method'], 'in', 'range' => [self::PAYMENT_METHOD_CARD, self::PAYMENT_METHOD_UA_PAY, self::PAYMENT_METHOD_BANK]],
            [['transaction_id', 'notes'], 'string'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function getAppointment()
    {
        return $this->hasOne(Appointment::class, ['id' => 'appointment_id']);
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Користувач',
            'appointment_id' => 'Запис',
            'amount' => 'Сума',
            'currency' => 'Валюта',
            'status' => 'Статус',
            'payment_method' => 'Метод оплати',
            'transaction_id' => 'ID транзакції',
            'notes' => 'Примітки',
        ];
    }
}
