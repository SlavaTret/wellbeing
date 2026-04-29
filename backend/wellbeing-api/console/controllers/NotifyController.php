<?php

namespace console\controllers;

use common\models\Appointment;
use common\services\NotificationService;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

// Cron every 30 minutes:  * /30 * * * * /path/to/yii notify/send-reminders >> /tmp/notify.log 2>&1
/**
 * Sends time-based appointment reminders.
 */
class NotifyController extends Controller
{
    /**
     * Finds appointments due in ~12 h or ~1 h and sends reminder notifications.
     */
    public function actionSendReminders(): int
    {
        $now     = time();
        $service = new NotificationService();

        $windows = [
            12 => [11 * 3600 + 30 * 60, 12 * 3600 + 30 * 60], // 11:30–12:30 ahead
            1  => [          30 * 60,           90 * 60],        //  0:30– 1:30 ahead
        ];

        $total = 0;

        foreach ($windows as $hoursLeft => [$minOffset, $maxOffset]) {
            $from = date('Y-m-d H:i:s', $now + $minOffset);
            $to   = date('Y-m-d H:i:s', $now + $maxOffset);
            $type = $hoursLeft >= 12 ? 'reminder_12h' : 'reminder_1h';

            $rows = Yii::$app->db->createCommand(
                "SELECT a.id
                 FROM appointment a
                 WHERE a.status IN ('confirmed', 'pending')
                   AND (a.appointment_date || ' ' || a.appointment_time)::timestamp BETWEEN :from AND :to
                   AND NOT EXISTS (
                       SELECT 1 FROM notification n
                       WHERE n.user_id = a.user_id
                         AND n.type = :type
                         AND n.related_appointment_id = a.id
                   )",
                [':from' => $from, ':to' => $to, ':type' => $type]
            )->queryAll();

            foreach ($rows as $row) {
                $appt = Appointment::findOne($row['id']);
                if (!$appt) continue;
                $service->notifyReminder($appt, $hoursLeft);
                $total++;
            }

            $this->stdout("  [{$type}] window {$from} — {$to}: " . count($rows) . " sent\n");
        }

        $this->stdout("notify/send-reminders done. Total sent: {$total}\n");
        return ExitCode::OK;
    }
}
