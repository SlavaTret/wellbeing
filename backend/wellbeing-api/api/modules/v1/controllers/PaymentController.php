<?php

namespace api\modules\v1\controllers;

use common\models\Appointment;
use common\models\Payment;
use common\services\PaymentService;
use Yii;
use yii\rest\Controller;

class PaymentController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = ['class' => \yii\filters\auth\HttpBearerAuth::class];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ];
        return $behaviors;
    }

    /** GET /v1/payment */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $payments = Payment::find()
            ->where(['user_id' => $userId])
            ->with('appointment')
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        $items   = array_map(fn($p) => $this->formatPayment($p), $payments);
        $pending = null;
        foreach ($items as $it) {
            if ($it['status'] === 'pending' && $pending === null) {
                $pending = $it;
            }
        }

        return ['items' => $items, 'pending' => $pending];
    }

    /** POST /v1/payment/<appointment_id>/initiate — create gateway checkout URL */
    public function actionInitiate($id)
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $appointment = Appointment::findOne(['id' => $id, 'user_id' => $userId]);
        if (!$appointment) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Appointment not found'];
        }

        if ($appointment->payment_status === 'paid') {
            return ['error' => 'Already paid'];
        }

        try {
            $result = (new PaymentService())->initiateAppointmentPayment((int)$id, $userId);
        } catch (\Throwable $e) {
            Yii::error('initiatePayment error: ' . $e->getMessage(), 'payment');
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Payment initiation failed'];
        }

        // Mark appointment as payment pending
        $appointment->payment_status = 'pending';
        $appointment->save(false);

        return $result;
    }

    /** POST /v1/payment/<id>/sync — poll gateway and sync status */
    public function actionSync($id)
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $payment = Payment::findOne(['id' => $id, 'user_id' => $userId]);
        if (!$payment) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Payment not found'];
        }

        $result = (new PaymentService())->syncPaymentStatus((int)$id);
        return $result;
    }

    /** POST /v1/payment/sync-by-order — sync by gateway_order_id (called on return from checkout) */
    public function actionSyncByOrder()
    {
        Yii::$app->response->format = 'json';
        $userId  = Yii::$app->user->id;
        $orderId = Yii::$app->request->post('order_id', '');

        if (!$orderId) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'order_id required'];
        }

        $paymentId = Yii::$app->db->createCommand(
            'SELECT id FROM payment WHERE gateway_order_id = :o AND user_id = :u LIMIT 1',
            [':o' => $orderId, ':u' => $userId]
        )->queryScalar();
        $payment = $paymentId ? Payment::findOne($paymentId) : null;
        if (!$payment) {
            return ['status' => 'not_found'];
        }

        $result = (new PaymentService())->syncPaymentStatus((int)$payment->id);
        return $result;
    }

    /** POST /v1/payment/<id>/process — legacy: mark as paid manually */
    public function actionProcess($id)
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $payment = Payment::findOne(['id' => $id, 'user_id' => $userId]);
        if (!$payment) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Payment not found'];
        }

        $data = Yii::$app->request->post();
        $payment->payment_method = $data['payment_method'] ?? 'card';
        $payment->transaction_id = 'TXN-' . time();
        $payment->status         = Payment::STATUS_COMPLETED;
        $payment->save(false);

        if ($payment->appointment_id) {
            $appt = Appointment::findOne($payment->appointment_id);
            if ($appt) {
                $appt->payment_status = 'paid';
                $appt->save(false);
            }
        }

        return ['success' => true, 'payment' => $this->formatPayment($payment)];
    }

    private function formatPayment(Payment $p): array
    {
        $appt = $p->appointment;

        return [
            'id'               => $p->id,
            'appointment_id'   => $p->appointment_id,
            'specialist'       => $appt?->specialist_name ?? '—',
            'specialist_type'  => $appt?->specialist_type ?? '',
            'date'             => $appt?->appointment_date
                ? $this->dateLabel($appt->appointment_date)
                : $this->dateLabel(date('Y-m-d', $p->created_at)),
            'amount'           => (float)$p->amount,
            'currency'         => $p->currency ?? 'UAH',
            'status'           => $p->status,
            'payment_method'   => $p->payment_method,
            'gateway'          => $p->gateway,
            'gateway_order_id' => $p->gateway_order_id,
            'transaction_id'   => $p->gateway_payment_id ?: $p->transaction_id,
            'paid_at'          => $p->paid_at,
            'created_at'       => $p->created_at,
        ];
    }

    private function dateLabel(string $ymd): string
    {
        $months = ['', 'січ.', 'лют.', 'бер.', 'квіт.', 'трав.', 'черв.', 'лип.', 'серп.', 'вер.', 'жовт.', 'лист.', 'груд.'];
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        if (!$dt) return $ymd;
        return $dt->format('j') . ' ' . $months[(int)$dt->format('n')] . ' ' . $dt->format('Y');
    }
}
