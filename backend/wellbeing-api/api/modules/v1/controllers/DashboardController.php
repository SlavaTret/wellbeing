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
        // Total limit comes from user's company; default 5 if no company linked.
        $freeTotal = 5;
        if ($user->company_id) {
            $company = Company::findOne($user->company_id);
            if ($company && $company->free_sessions_per_user > 0) {
                $freeTotal = (int)$company->free_sessions_per_user;
            }
        }

        // "Used" = all non-cancelled appointments (company covers them)
        $usedFree = Appointment::find()->where(['user_id' => $userId])
            ->andWhere(['NOT IN', 'status', [Appointment::STATUS_CANCELLED]])
            ->count();

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

        $upcomingFormatted = array_map(function (Appointment $a) use ($ukMonths) {
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
                'date'       => $date,
                'date_raw'   => $a->appointment_date,
                'time'       => $a->appointment_time,
                'status'     => $a->status,
                'avatar'     => $avatar,
            ];
        }, $nextAppointments);

        return [
            'stats' => [
                'upcoming'          => (int)$upcoming,
                'completed'         => (int)$completed,
                'cancelled'         => (int)$cancelled,
                'free_remaining'    => $remainingFree,
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
}
