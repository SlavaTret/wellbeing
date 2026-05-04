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

    /** GET /v1/debug/liqpay — diagnostic: verify keys and generate a test checkout URL */
    public function actionDebugLiqpay()
    {
        Yii::$app->response->format = 'json';

        $publicKey  = \common\models\AppSettings::get('liqpay_public_key');
        $privateKey = \common\models\AppSettings::get('liqpay_private_key');

        // Build minimal test params
        $testOrderId = 'DEBUG-' . time();
        $params = [
            'version'     => 3,
            'public_key'  => $publicKey,
            'action'      => 'pay',
            'amount'      => '10.00',
            'currency'    => 'UAH',
            'description' => 'Debug test',
            'order_id'    => $testOrderId,
            'result_url'  => 'https://example.com/ok',
            'server_url'  => 'https://httpbin.org/post',
            'language'    => 'uk',
        ];

        $data      = base64_encode(json_encode($params));
        $signature = base64_encode(sha1($privateKey . $data . $privateKey, true));
        $checkoutUrl = 'https://www.liqpay.ua/api/3/checkout?data=' . urlencode($data) . '&signature=' . urlencode($signature);

        // Test actual LiqPay API with status request for a fake order
        $statusParams = [
            'version'    => 3,
            'public_key' => $publicKey,
            'action'     => 'status',
            'order_id'   => 'PROBE-' . time(),
        ];
        $statusData = base64_encode(json_encode($statusParams));
        $statusSig  = base64_encode(sha1($privateKey . $statusData . $privateKey, true));

        $ch = curl_init('https://www.liqpay.ua/api/request');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['data' => $statusData, 'signature' => $statusSig]));
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        $apiResponse = json_decode($raw ?: '{}', true) ?? [];

        return [
            'public_key'        => $publicKey,
            'public_key_length' => strlen($publicKey),
            'private_key_set'   => !empty($privateKey),
            'private_key_length'=> strlen($privateKey),
            'private_key_prefix'=> substr($privateKey, 0, 8) . '...',
            'test_params'       => $params,
            'generated_data_b64'=> $data,
            'generated_sig'     => $signature,
            'checkout_url'      => $checkoutUrl,
            'api_status_probe'  => [
                'raw_response' => $raw,
                'parsed'       => $apiResponse,
                'curl_error'   => $curlError,
            ],
        ];
    }
}
