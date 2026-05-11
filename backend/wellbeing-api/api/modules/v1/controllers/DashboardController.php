<?php

namespace api\modules\v1\controllers;

use common\models\Appointment;
use common\models\Company;
use Yii;
use yii\rest\Controller;

class DashboardController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        $user   = Yii::$app->user->identity;
        $userId = $user->id;

        // ── Appointment counts ─────────────────────────────────
        $upcoming  = Appointment::find()->where(['user_id' => $userId])
            ->andWhere(['IN', 'status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PENDING]])
            ->count();

        $completed = Appointment::find()->where(['user_id' => $userId])
            ->andWhere(['status' => Appointment::STATUS_COMPLETED])
            ->count();

        $cancelled = Appointment::find()->where(['user_id' => $userId])
            ->andWhere(['status' => Appointment::STATUS_CANCELLED])
            ->count();

        // ── Free sessions ──────────────────────────────────────
        $freeTotal = 0;
        if ($user->company_id) {
            $company = Company::findOne($user->company_id);
            if ($company && $company->free_sessions_per_user > 0) {
                $freeTotal = (int)$company->free_sessions_per_user;
            }
        }

        // "Used" = subscription-covered appointments this calendar month (no payment record = subscription)
        $usedFree = (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM appointment a
             WHERE a.user_id = :uid
               AND a.status NOT IN (:cancelled)
               AND a.payment_status = :paid
               AND DATE_TRUNC('month', a.appointment_date::date) = DATE_TRUNC('month', CURRENT_DATE)
               AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.appointment_id = a.id)",
            [':uid' => $userId, ':cancelled' => Appointment::STATUS_CANCELLED, ':paid' => 'paid']
        )->queryScalar();

        $remainingFree     = max(0, $freeTotal - $usedFree);
        $freeProgressPct   = $freeTotal > 0 ? round(($usedFree / $freeTotal) * 100) : 0;

        // ── Next 2 upcoming appointments ───────────────────────
        $nextAppointments = Appointment::find()
            ->where(['user_id' => $userId])
            ->andWhere(['IN', 'status', [Appointment::STATUS_CONFIRMED, Appointment::STATUS_PENDING]])
            ->orderBy(['appointment_date' => SORT_ASC, 'appointment_time' => SORT_ASC])
            ->limit(3)
            ->all();

        $ukMonths = ['', 'січня','лютого','березня','квітня','травня','червня',
                         'липня','серпня','вересня','жовтня','листопада','грудня'];

        // Pre-fetch specialization names for all unique types in one query
        $typeKeys = array_unique(array_column(
            array_map(fn($a) => ['type' => $a->specialist_type], $nextAppointments),
            'type'
        ));
        $typeNames = [];
        if ($typeKeys) {
            $params = [];
            $placeholders = [];
            foreach (array_values($typeKeys) as $i => $key) {
                $param = ':tk' . $i;
                $placeholders[] = $param;
                $params[$param] = $key;
            }
            $rows = Yii::$app->db->createCommand(
                'SELECT key, name FROM specialization WHERE key IN (' . implode(',', $placeholders) . ')',
                $params
            )->queryAll();
            foreach ($rows as $row) {
                $typeNames[$row['key']] = $row['name'];
            }
        }

        $upcomingFormatted = array_map(function (Appointment $a) use ($ukMonths, $typeNames) {
            $d    = \DateTime::createFromFormat('Y-m-d', $a->appointment_date);
            $date = $d
                ? $d->format('j') . ' ' . $ukMonths[(int)$d->format('n')]
                : $a->appointment_date;

            $name = $a->specialist_name;
            $avatar = mb_strtoupper(
                mb_substr($name, 0, 1) .
                (($pos = mb_strpos($name, ' ')) !== false
                    ? mb_substr($name, $pos + 1, 1)
                    : '')
            );

            return [
                'id'         => $a->id,
                'specialist' => $a->specialist_name,
                'type'       => $a->specialist_type,
                'type_name'  => $typeNames[$a->specialist_type] ?? $a->specialist_type,
                'date'       => $date,
                'date_raw'   => $a->appointment_date,
                'time'       => $a->appointment_time,
                'status'     => $a->status,
                'avatar'     => $avatar,
            ];
        }, $nextAppointments);

        return [
            'stats' => [
                'upcoming'       => (int)$upcoming,
                'completed'      => (int)$completed,
                'cancelled'      => (int)$cancelled,
                'free_remaining' => $remainingFree,
            ],
            'free_sessions' => [
                'total'       => $freeTotal,
                'used'        => (int)$usedFree,
                'remaining'   => $remainingFree,
                'percent'     => $freeProgressPct,
            ],
            'upcoming_appointments' => $upcomingFormatted,
        ];
    }

    public function actionFreeSessions()
    {
        Yii::$app->response->format = 'json';
        $user   = Yii::$app->user->identity;
        $userId = $user->id;

        $freeTotal = 0;
        if ($user->company_id) {
            $company = Company::findOne($user->company_id);
            if ($company && $company->free_sessions_per_user > 0) {
                $freeTotal = (int)$company->free_sessions_per_user;
            }
        }

        $usedFree = (int)Yii::$app->db->createCommand(
            "SELECT COUNT(*) FROM appointment a
             WHERE a.user_id = :uid
               AND a.status NOT IN (:cancelled)
               AND a.payment_status = :paid
               AND DATE_TRUNC('month', a.appointment_date::date) = DATE_TRUNC('month', CURRENT_DATE)
               AND NOT EXISTS (SELECT 1 FROM payment p WHERE p.appointment_id = a.id)",
            [':uid' => $userId, ':cancelled' => Appointment::STATUS_CANCELLED, ':paid' => 'paid']
        )->queryScalar();

        $remaining = max(0, $freeTotal - $usedFree);
        $percent   = $freeTotal > 0 ? round(($usedFree / $freeTotal) * 100) : 0;

        return [
            'total'     => $freeTotal,
            'used'      => $usedFree,
            'remaining' => $remaining,
            'percent'   => $percent,
        ];
    }
}
