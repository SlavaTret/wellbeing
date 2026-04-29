<?php

namespace common\services;

use common\contracts\PaymentGatewayInterface;
use common\models\AppSettings;
use common\models\Appointment;
use common\models\NotificationSetting;
use common\models\Payment;
use common\models\UserGoogleToken;
use common\services\GoogleCalendarService;
use common\services\payment\LiqPayGateway;
use common\services\payment\UaPayGateway;
use Yii;

class PaymentService
{
    public function getGateway(): PaymentGatewayInterface
    {
        $active = AppSettings::get('active_gateway', 'liqpay');
        return $active === 'uapay' ? new UaPayGateway() : new LiqPayGateway();
    }

    public function getActiveGatewayName(): string
    {
        return AppSettings::get('active_gateway', 'liqpay');
    }

    /**
     * Called after appointment creation when payment is required.
     * Creates a Payment row and returns ['checkout_url' => string, 'payment_id' => int].
     */
    public function initiateAppointmentPayment(int $appointmentId, int $userId): array
    {
        $appointment = Appointment::findOne($appointmentId);
        if (!$appointment) {
            throw new \RuntimeException('Appointment not found');
        }

        $gateway     = $this->getGateway();
        $gatewayName = $this->getActiveGatewayName();
        $appUrl      = rtrim(AppSettings::get('app_url', 'http://localhost:4200'), '/');

        $orderId = 'WB-' . $appointmentId . '-' . time();

        $order = [
            'order_id'    => $orderId,
            'amount'      => (float)$appointment->price,
            'currency'    => 'UAH',
            'description' => 'Консультація: ' . $appointment->specialist_name . ', ' . $appointment->appointment_date,
            'return_url'  => $appUrl . '/appointments?payment=success&order=' . $orderId,
            'callback_url'=> $appUrl . '/api/v1/payment/callback/' . $gatewayName,
        ];

        $result = $gateway->createPayment($order);

        // Find existing pending payment or create new (save only base fields via AR)
        $payment = Payment::findOne(['appointment_id' => $appointmentId, 'status' => Payment::STATUS_PENDING]);
        if (!$payment) {
            $payment = new Payment();
            $payment->user_id        = $userId;
            $payment->appointment_id = $appointmentId;
            $payment->amount         = (float)$appointment->price;
            $payment->currency       = 'UAH';
            $payment->status         = Payment::STATUS_PENDING;
            $payment->payment_method = $gatewayName;
            $payment->save(false);
        }

        // Update gateway-specific columns via raw SQL to avoid schema-cache issues
        Yii::$app->db->createCommand()->update('payment', [
            'gateway'          => $gatewayName,
            'gateway_order_id' => $orderId,
            'description'      => $order['description'],
        ], ['id' => $payment->id])->execute();

        return [
            'checkout_url' => $result['checkout_url'],
            'payment_id'   => $payment->id,
        ];
    }

    /**
     * Processes gateway callback. Idempotent — safe to call multiple times.
     */
    public function handleCallback(string $gatewayName, array $data): void
    {
        $gateway = $gatewayName === 'uapay' ? new UaPayGateway() : new LiqPayGateway();

        if (!$gateway->verifyCallback($data)) {
            Yii::warning('Payment callback signature mismatch for gateway: ' . $gatewayName, 'payment');
            throw new \RuntimeException('Invalid signature');
        }

        $parsed  = $gateway->parseCallback($data);
        $orderId = $parsed['order_id'] ?? '';

        if (!$orderId) {
            return;
        }

        $payment = Payment::findOne(['gateway_order_id' => $orderId]);
        if (!$payment) {
            Yii::warning('Payment not found for order_id: ' . $orderId, 'payment');
            return;
        }

        // Idempotency — skip if already finalized
        if (in_array($payment->status, [Payment::STATUS_COMPLETED, Payment::STATUS_FAILED])) {
            return;
        }

        if ($parsed['status'] === 'success') {
            $payment->status = Payment::STATUS_COMPLETED;
            $payment->save(false);

            Yii::$app->db->createCommand()->update('payment', [
                'gateway_payment_id' => $parsed['payment_id'] ?? '',
                'raw_response'       => json_encode($data),
                'paid_at'            => time(),
            ], ['id' => $payment->id])->execute();

            if ($payment->appointment_id) {
                Yii::$app->db->createCommand()->update(
                    'appointment',
                    ['payment_status' => 'paid', 'status' => 'confirmed'],
                    ['id' => $payment->appointment_id]
                )->execute();

                $this->addToCalendarAfterPayment($payment->appointment_id, $payment->user_id);
            }
        } else {
            $payment->status = Payment::STATUS_FAILED;
            $payment->save(false);

            Yii::$app->db->createCommand()->update('payment', [
                'gateway_payment_id' => $parsed['payment_id'] ?? '',
                'raw_response'       => json_encode($data),
            ], ['id' => $payment->id])->execute();

            if ($payment->appointment_id) {
                Yii::$app->db->createCommand()->update(
                    'appointment',
                    ['payment_status' => 'failed'],
                    ['id' => $payment->appointment_id]
                )->execute();
            }
        }
    }

    /**
     * Polls gateway for current payment status and syncs local record.
     */
    public function syncPaymentStatus(int $paymentId): array
    {
        $payment = Payment::findOne($paymentId);
        if (!$payment) {
            return ['status' => 'unknown'];
        }

        // Read new columns via raw SQL to bypass schema cache
        $row = Yii::$app->db->createCommand(
            'SELECT gateway, gateway_order_id FROM payment WHERE id = :id',
            [':id' => $paymentId]
        )->queryOne();

        $gatewayName = $row['gateway'] ?? '';
        $orderId     = $row['gateway_order_id'] ?? '';

        if (!$orderId) {
            return ['status' => 'unknown'];
        }

        $gateway = $gatewayName === 'uapay' ? new UaPayGateway() : new LiqPayGateway();
        $result  = $gateway->getStatus($orderId);

        if ($result['status'] === 'success' && $payment->status !== Payment::STATUS_COMPLETED) {
            $payment->status = Payment::STATUS_COMPLETED;
            $payment->save(false);

            Yii::$app->db->createCommand()->update('payment', [
                'gateway_payment_id' => $result['payment_id'] ?? '',
                'paid_at'            => time(),
            ], ['id' => $payment->id])->execute();

            if ($payment->appointment_id) {
                Yii::$app->db->createCommand()->update(
                    'appointment',
                    ['payment_status' => 'paid', 'status' => 'confirmed'],
                    ['id' => $payment->appointment_id]
                )->execute();

                $this->addToCalendarAfterPayment($payment->appointment_id, $payment->user_id);
            }
        }

        return ['status' => $result['status'], 'payment_id' => $payment->id];
    }

    private function addToCalendarAfterPayment(int $appointmentId, int $userId): void
    {
        try {
            $settings = NotificationSetting::forUser($userId);
            if (!$settings->calendar_enabled) {
                return;
            }

            $token = UserGoogleToken::forUser($userId);
            if (!$token) {
                return;
            }

            $appt = Appointment::findOne($appointmentId);
            if (!$appt) {
                return;
            }

            $specialistEmail = '';
            if ($appt->specialist_id) {
                $specialistEmail = (string)Yii::$app->db->createCommand(
                    'SELECT email FROM specialist WHERE id = :id',
                    [':id' => $appt->specialist_id]
                )->queryScalar();
            }

            $svc    = new GoogleCalendarService();
            $result = $svc->createEventWithMeet($token, [
                'appointment_date' => $appt->appointment_date,
                'appointment_time' => $appt->appointment_time,
                'specialist_name'  => $appt->specialist_name,
                'book_via'         => 'online',
            ], $specialistEmail ?: '');

            if (!empty($result['event_id'])) {
                Yii::$app->db->createCommand()->update('appointment', [
                    'google_event_id'  => $result['event_id'],
                    'google_meet_link' => $result['meet_link'],
                ], ['id' => $appointmentId])->execute();
            }
        } catch (\Throwable $e) {
            Yii::error('Google Calendar after payment failed: ' . $e->getMessage(), 'google');
        }
    }
}
