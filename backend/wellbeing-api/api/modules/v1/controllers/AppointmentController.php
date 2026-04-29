<?php

namespace api\modules\v1\controllers;

use common\models\Appointment;
use common\models\Company;
use common\models\NotificationSetting;
use common\models\Payment;
use common\models\SpecialistReview;
use common\models\UserGoogleToken;
use common\services\GoogleCalendarService;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class AppointmentController extends Controller
{
    public $modelClass = 'common\models\Appointment';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@'],
                ],
            ],
        ];
        return $behaviors;
    }

    /**
     * List appointments for current user
     */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        $query = Appointment::find()->where(['user_id' => $user->id]);

        // Filter by status
        $status = Yii::$app->request->get('status');
        if ($status) {
            $query->andWhere(['status' => $status]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['appointment_date' => SORT_DESC],
            ],
        ]);

        $models = $dataProvider->getModels();

        // Batch-load review rating per appointment (appointment_id → rating)
        $ids = array_map(fn($a) => $a->id, $models);
        $reviewRatings = [];
        if ($ids) {
            $rows = SpecialistReview::find()
                ->select(['appointment_id', 'rating'])
                ->where(['appointment_id' => $ids])
                ->asArray()
                ->all();
            foreach ($rows as $r) {
                $reviewRatings[$r['appointment_id']] = (int)$r['rating'];
            }
        }

        $items = array_map(function ($a) use ($reviewRatings) {
            return $this->formatAppointment($a, $reviewRatings[$a->id] ?? null);
        }, $models);

        return [
            'items' => $items,
            'total' => $dataProvider->totalCount,
        ];
    }

    /**
     * Get single appointment
     */
    public function actionView($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        
        $this->checkUserAccess($appointment->user_id);

        return $this->formatAppointment($appointment);
    }

    /**
     * Create appointment
     */
    public function actionCreate()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        $appointment = new Appointment();
        $appointment->user_id = $user->id;

        $data = Yii::$app->request->post();
        if (!$appointment->load($data, '') || !$appointment->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $appointment->getErrors()];
        }

        if ($appointment->save()) {
            // Pull price from specialist record if not provided by client
            if ((float)$appointment->price <= 0 && $appointment->specialist_id) {
                $specialistPrice = (float)Yii::$app->db->createCommand(
                    'SELECT price FROM specialist WHERE id = :id',
                    [':id' => $appointment->specialist_id]
                )->queryScalar();
                if ($specialistPrice > 0) {
                    $appointment->price = $specialistPrice;
                    $appointment->save(false);
                }
            }
            $this->handlePaymentForNewAppointment($user, $appointment);
            // Add to Google Calendar only if already paid (free session or zero price).
            // For paid appointments, calendar is added after payment confirmation.
            if ($appointment->payment_status === 'paid') {
                $this->addToGoogleCalendar($user, $appointment, $data);
                (new \common\services\NotificationService())->notifyAppointmentConfirmed($appointment);
            }
            Yii::$app->response->statusCode = 201;
            return $this->formatAppointment($appointment);
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create appointment'];
    }

    /**
     * Update appointment
     */
    public function actionUpdate($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        $prevStatus = $appointment->status;
        $data = Yii::$app->request->post();
        if (!$appointment->load($data, '') || !$appointment->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $appointment->getErrors()];
        }

        if ($appointment->save()) {
            if ($prevStatus !== Appointment::STATUS_COMPLETED && $appointment->status === Appointment::STATUS_COMPLETED) {
                (new \common\services\NotificationService())->notifyReviewRequest($appointment);
            }
            return $appointment;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to update appointment'];
    }

    /**
     * Delete appointment
     */
    public function actionDelete($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        if ($appointment->delete()) {
            Yii::$app->response->statusCode = 204;
            return null;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to delete appointment'];
    }

    /**
     * Cancel appointment with optional refund
     */
    public function actionCancel($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        // Determine payment type via raw SQL (gateway columns bypass schema cache)
        // Include STATUS_REFUNDED in case a previous cancel attempt already refunded the payment
        // but failed to update the appointment row (e.g. constraint violation).
        $paidRow = Yii::$app->db->createCommand(
            'SELECT id, status FROM payment WHERE appointment_id = :a AND status IN (:s1, :s2) LIMIT 1',
            [':a' => $appointment->id, ':s1' => \common\models\Payment::STATUS_COMPLETED, ':s2' => \common\models\Payment::STATUS_REFUNDED]
        )->queryOne();

        $cancelType    = 'none';
        $refundSuccess = false;
        $refundAmount  = 0.0;

        if ($paidRow) {
            $cancelType = 'gateway_paid';
            $refund     = (new \common\services\PaymentService())->refundForAppointment((int)$appointment->id);
            $refundSuccess = $refund['success'] ?? false;
            $refundAmount  = $refund['amount']  ?? 0.0;
        } elseif ($appointment->payment_status === 'paid') {
            $cancelType = 'free_session';
        }

        // Update appointment status and payment_status
        $newPaymentStatus = $appointment->payment_status;
        if ($cancelType === 'gateway_paid') {
            $newPaymentStatus = $refundSuccess ? 'refunded' : 'failed';
        }

        Yii::$app->db->createCommand()->update('appointment', [
            'status'         => Appointment::STATUS_CANCELLED,
            'payment_status' => $newPaymentStatus,
        ], ['id' => $appointment->id])->execute();

        $appointment->status         = Appointment::STATUS_CANCELLED;
        $appointment->payment_status = $newPaymentStatus;

        // Remove from Google Calendar (silently)
        $this->removeFromGoogleCalendar($appointment);

        // Send refund notification if money was returned
        if ($cancelType === 'gateway_paid' && $refundSuccess) {
            (new \common\services\NotificationService())->notifyRefund($appointment, $refundAmount);
        }

        $response = [
            'success'      => true,
            'cancel_type'  => $cancelType,
            'appointment'  => $this->formatAppointment($appointment),
        ];
        if ($cancelType === 'gateway_paid') {
            $response['refund_success'] = $refundSuccess;
            $response['refund_amount']  = $refundAmount;
        }
        return $response;
    }

    /**
     * Leave review
     */
    public function actionReview($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        $data = Yii::$app->request->post();
        $appointment->notes = $data['notes'] ?? $appointment->notes;
        $appointment->status = Appointment::STATUS_COMPLETED;

        if ($appointment->save(false)) {
            return [
                'success' => true,
                'message' => 'Відгук збережено',
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to save review'];
    }

    private function addToGoogleCalendar($user, Appointment $appointment, array $data): void
    {
        try {
            $settings = NotificationSetting::forUser($user->id);
            if (!$settings->calendar_enabled) {
                return;
            }

            $token = UserGoogleToken::forUser($user->id);
            if (!$token) {
                return;
            }

            // Get specialist email if available
            $specialistEmail = '';
            if ($appointment->specialist_id) {
                $specialistEmail = (string)Yii::$app->db->createCommand(
                    'SELECT email FROM specialist WHERE id = :id',
                    [':id' => $appointment->specialist_id]
                )->queryScalar();
            }

            $svc = new GoogleCalendarService();
            $result = $svc->createEventWithMeet($token, [
                'appointment_date' => $appointment->appointment_date,
                'appointment_time' => $appointment->appointment_time,
                'specialist_name'  => $appointment->specialist_name,
                'book_via'         => $data['book_via'] ?? 'online',
            ], $specialistEmail ?: '');

            if ($result['event_id']) {
                Yii::$app->db->createCommand()->update(
                    'appointment',
                    [
                        'google_event_id' => $result['event_id'],
                        'google_meet_link'=> $result['meet_link'],
                    ],
                    ['id' => $appointment->id]
                )->execute();

                $appointment->google_event_id  = $result['event_id'];
                $appointment->google_meet_link = $result['meet_link'];
            }
        } catch (\Throwable $e) {
            Yii::error('Google Calendar create failed: ' . $e->getMessage(), 'google');
        }
    }

    private function removeFromGoogleCalendar(Appointment $appointment): void
    {
        try {
            if (!$appointment->google_event_id) {
                return;
            }

            $token = UserGoogleToken::forUser($appointment->user_id);
            if (!$token) {
                return;
            }

            (new GoogleCalendarService())->deleteEvent($token, $appointment->google_event_id);

            Yii::$app->db->createCommand()->update(
                'appointment',
                ['google_event_id' => null, 'google_meet_link' => null],
                ['id' => $appointment->id]
            )->execute();
        } catch (\Throwable $e) {
            Yii::error('Google Calendar delete failed: ' . $e->getMessage(), 'google');
        }
    }

    private function handlePaymentForNewAppointment($user, Appointment $appointment): void
    {
        $price = (float)$appointment->price;
        if ($price <= 0) {
            $appointment->payment_status = 'paid';
            $appointment->status = Appointment::STATUS_CONFIRMED;
            $appointment->save(false);
            return;
        }

        // Determine free sessions quota
        $freeTotal = 0;
        if ($user->company_id) {
            $company = Company::findOne($user->company_id);
            if ($company && $company->free_sessions_per_user > 0) {
                $freeTotal = (int)$company->free_sessions_per_user;
            }
        }

        if ($freeTotal > 0) {
            // Count non-cancelled appointments excluding this new one
            $usedFree = (int)Appointment::find()
                ->where(['user_id' => $user->id])
                ->andWhere(['NOT IN', 'status', [Appointment::STATUS_CANCELLED]])
                ->andWhere(['!=', 'id', $appointment->id])
                ->count();
            $freeRemaining = max(0, $freeTotal - $usedFree);
        } else {
            $freeRemaining = 0;
        }

        if ($freeRemaining > 0) {
            $appointment->payment_status = 'paid';
            $appointment->status = Appointment::STATUS_CONFIRMED;
            $appointment->save(false);
        } else {
            $appointment->payment_status = 'pending';
            $appointment->save(false);

            $payment = new Payment();
            $payment->user_id = $user->id;
            $payment->appointment_id = $appointment->id;
            $payment->amount = $price;
            $payment->currency = 'UAH';
            $payment->status = Payment::STATUS_PENDING;
            $payment->payment_method = Payment::PAYMENT_METHOD_CARD;
            $payment->save();
        }
    }

    private function formatAppointment($a, ?int $reviewRating = null): array
    {
        $ukrainianMonths = ['', 'січня','лютого','березня','квітня','травня','червня','липня','серпня','вересня','жовтня','листопада','грудня'];
        $date = \DateTime::createFromFormat('Y-m-d', $a->appointment_date);
        $dateLabel = $date
            ? $date->format('j') . ' ' . $ukrainianMonths[(int)$date->format('n')] . ' ' . $date->format('Y')
            : $a->appointment_date;

        $gatewayPaid = (bool)Yii::$app->db->createCommand(
            'SELECT 1 FROM payment WHERE appointment_id = :id AND status IN (:s1, :s2) LIMIT 1',
            [':id' => $a->id, ':s1' => 'completed', ':s2' => 'refunded']
        )->queryScalar();

        // If no payment gateway transaction, it means it was covered by corporate subscription
        $paymentStatus = $a->payment_status;
        if (!$gatewayPaid && $a->price > 0) {
            $paymentStatus = 'subscription';
        }

        return [
            'id'               => $a->id,
            'specialist_id'    => $a->specialist_id,
            'specialist'       => $a->specialist_name,
            'type'             => $a->specialist_type,
            'date'             => $dateLabel,
            'date_raw'         => $a->appointment_date,
            'time'             => $a->appointment_time,
            'status'           => $a->status,
            'paid'             => $a->payment_status === 'paid',
            'gateway_paid'     => $gatewayPaid,
            'payment_status'   => $paymentStatus,
            'notes'            => $a->notes,
            'price'            => (float)$a->price,
            'review_rating'    => $reviewRating,
            'avatar'           => mb_strtoupper(
                mb_substr($a->specialist_name, 0, 1) .
                (strpos($a->specialist_name, ' ') !== false
                    ? mb_substr(substr($a->specialist_name, strpos($a->specialist_name, ' ') + 1), 0, 1)
                    : '')
            ),
        ];
    }

    private function findModel($id)
    {
        $model = Appointment::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Appointment not found');
        }
        return $model;
    }

    private function checkUserAccess($userId)
    {
        $user = Yii::$app->user->identity;
        if ($user->id !== $userId) {
            Yii::$app->response->statusCode = 403;
            throw new \yii\web\ForbiddenHttpException('Access denied');
        }
    }
}
