<?php

namespace common\services;

use common\models\Appointment;
use common\models\Notification;
use common\models\NotificationSetting;
use Yii;

class NotificationService
{
    /**
     * Base method — inserts a notification row via raw SQL to bypass schema cache.
     */
    public function createForUser(
        int    $userId,
        string $type,
        string $title,
        string $message,
        ?int   $relatedAppointmentId = null,
        array  $data = []
    ): void {
        try {
            Yii::$app->db->createCommand()->insert('notification', [
                'user_id'                => $userId,
                'type'                   => $type,
                'title'                  => $title,
                'message'                => $message,
                'is_read'                => false,
                'related_appointment_id' => $relatedAppointmentId,
                'data'                   => $data ? json_encode($data, JSON_UNESCAPED_UNICODE) : null,
                'created_at'             => time(),
                'updated_at'             => time(),
            ])->execute();
        } catch (\Throwable $e) {
            Yii::error('NotificationService::createForUser failed: ' . $e->getMessage(), 'notification');
        }
    }

    /**
     * Sent after appointment is confirmed and paid (or free).
     */
    public function notifyAppointmentConfirmed(Appointment $appt): void
    {
        $date = $this->formatDate($appt->appointment_date);
        $this->createForUser(
            $appt->user_id,
            Notification::TYPE_APPOINTMENT_CONFIRMED,
            'Запис підтверджено',
            "Ваш запис до {$appt->specialist_name} на {$date} о {$appt->appointment_time} підтверджено та оплачено.",
            $appt->id
        );
    }

    /**
     * Reminder sent by cron — hoursLeft is either 12 or 1.
     * Respects user's reminders_enabled setting. Idempotent.
     */
    public function notifyReminder(Appointment $appt, int $hoursLeft): void
    {
        $settings = NotificationSetting::forUser($appt->user_id);
        if (!$settings->reminders_enabled) {
            return;
        }

        $type = $hoursLeft >= 12
            ? Notification::TYPE_REMINDER_12H
            : Notification::TYPE_REMINDER_1H;

        // Idempotency — skip if already sent
        $exists = Yii::$app->db->createCommand(
            'SELECT 1 FROM notification WHERE user_id = :u AND type = :t AND related_appointment_id = :a LIMIT 1',
            [':u' => $appt->user_id, ':t' => $type, ':a' => $appt->id]
        )->queryScalar();

        if ($exists) {
            return;
        }

        $date     = $this->formatDate($appt->appointment_date);
        $hourWord = match ($hoursLeft) {
            1       => 'годину',
            2, 3, 4 => 'години',
            default => 'годин',
        };

        $this->createForUser(
            $appt->user_id,
            $type,
            'Нагадування про консультацію',
            "Через {$hoursLeft} {$hourWord} у вас консультація з {$appt->specialist_name}. {$date} о {$appt->appointment_time}.",
            $appt->id
        );
    }

    /**
     * Sent after successful refund on appointment cancellation.
     */
    public function notifyRefund(Appointment $appt, float $amount): void
    {
        $date            = $this->formatDate($appt->appointment_date);
        $amountFormatted = number_format($amount, 0, '.', ' ');

        $this->createForUser(
            $appt->user_id,
            Notification::TYPE_REFUND_PROCESSED,
            'Повернення коштів',
            "Запис до {$appt->specialist_name} на {$date} скасовано. Сума {$amountFormatted}\u{a0}грн буде зарахована на ваш рахунок протягом 3–5 робочих днів.",
            $appt->id,
            [
                'amount'          => $amount,
                'specialist_name' => $appt->specialist_name,
                'date'            => $appt->appointment_date,
            ]
        );
    }

    /**
     * Sent after appointment status changes to 'completed'. Idempotent.
     */
    public function notifyReviewRequest(Appointment $appt): void
    {
        // Idempotency — one review request per appointment
        $exists = Yii::$app->db->createCommand(
            'SELECT 1 FROM notification WHERE user_id = :u AND type = :t AND related_appointment_id = :a LIMIT 1',
            [':u' => $appt->user_id, ':t' => Notification::TYPE_REVIEW_REQUEST, ':a' => $appt->id]
        )->queryScalar();

        if ($exists) {
            return;
        }

        $this->createForUser(
            $appt->user_id,
            Notification::TYPE_REVIEW_REQUEST,
            'Як пройшла консультація?',
            "Ваша консультація з {$appt->specialist_name} завершена. Поділіться враженнями — це допоможе іншим.",
            $appt->id,
            [
                'appointment_id'  => $appt->id,
                'specialist_id'   => $appt->specialist_id,
                'specialist_name' => $appt->specialist_name,
            ]
        );
    }

    private function formatDate(string $ymd): string
    {
        $ukMonths = [
            '', 'січня', 'лютого', 'березня', 'квітня', 'травня', 'червня',
            'липня', 'серпня', 'вересня', 'жовтня', 'листопада', 'грудня',
        ];
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        if (!$dt) return $ymd;
        return $dt->format('j') . ' ' . $ukMonths[(int)$dt->format('n')];
    }
}
