<?php

namespace api\modules\v1\controllers;

use common\models\Appointment;
use common\models\Payment;
use common\models\Notification;
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
     * Get dashboard statistics
     */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        $userId = $user->id;

        // Get appointment counts
        $scheduledCount = Appointment::find()
            ->where(['user_id' => $userId])
            ->andWhere(['status' => Appointment::STATUS_CONFIRMED])
            ->count();

        $completedCount = Appointment::find()
            ->where(['user_id' => $userId])
            ->andWhere(['status' => Appointment::STATUS_COMPLETED])
            ->count();

        $cancelledCount = Appointment::find()
            ->where(['user_id' => $userId])
            ->andWhere(['status' => Appointment::STATUS_CANCELLED])
            ->count();

        // Get free sessions info (mock data, should be from subscription)
        $freeSessions = 6;
        $usedFreeSessions = $completedCount;
        $remainingFreeSessions = max(0, $freeSessions - $usedFreeSessions);
        $freeSessionsProgress = ($freeSessions > 0) ? ($usedFreeSessions / $freeSessions) * 100 : 0;

        // Get upcoming appointments
        $upcomingAppointments = Appointment::find()
            ->where(['user_id' => $userId])
            ->andWhere(['OR', 
                ['status' => Appointment::STATUS_CONFIRMED],
                ['status' => Appointment::STATUS_PENDING]
            ])
            ->andWhere(['>=', 'appointment_date', date('Y-m-d')])
            ->orderBy(['appointment_date' => SORT_ASC, 'appointment_time' => SORT_ASC])
            ->limit(2)
            ->all();

        // Get unread notifications count
        $unreadNotifications = Notification::find()
            ->where(['user_id' => $userId, 'is_read' => false])
            ->count();

        return [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar_url' => $user->avatar_url,
            ],
            'stats' => [
                'scheduled_consultations' => $scheduledCount,
                'completed_consultations' => $completedCount,
                'free_sessions_remaining' => $remainingFreeSessions,
                'cancelled_appointments' => $cancelledCount,
            ],
            'free_sessions' => [
                'total' => $freeSessions,
                'used' => $usedFreeSessions,
                'remaining' => $remainingFreeSessions,
                'progress_percentage' => round($freeSessionsProgress, 2),
            ],
            'upcoming_appointments' => $upcomingAppointments,
            'unread_notifications_count' => $unreadNotifications,
        ];
    }
}
