<?php

namespace api\modules\v1\controllers;

use common\models\Appointment;
use common\models\Payment;
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

    /** GET /v1/payment — list current user's payments + pending one separately */
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
                $pending = $it; // most recent pending
            }
        }

        return [
            'items'   => $items,
            'pending' => $pending,
        ];
    }

    /** POST /v1/payment/<id>/process — mark as paid */
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

        // Mirror payment_status on the linked appointment
        if ($payment->appointment_id) {
            $appt = Appointment::findOne($payment->appointment_id);
            if ($appt) {
                $appt->payment_status = 'paid';
                $appt->save(false);
            }
        }

        return [
            'success' => true,
            'payment' => $this->formatPayment($payment),
        ];
    }

    private function formatPayment(Payment $p): array
    {
        $appt = $p->appointment;

        return [
            'id'             => $p->id,
            'appointment_id' => $p->appointment_id,
            'specialist'     => $appt?->specialist_name ?? '—',
            'specialist_type'=> $appt?->specialist_type ?? '',
            'date'           => $appt?->appointment_date ? $this->dateLabel($appt->appointment_date) : $this->dateLabel(date('Y-m-d', $p->created_at)),
            'amount'         => (float)$p->amount,
            'currency'       => $p->currency ?? 'UAH',
            'status'         => $p->status,            // pending | completed | failed | refunded
            'payment_method' => $p->payment_method,
            'created_at'     => $p->created_at,
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
