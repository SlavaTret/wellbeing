<?php

use yii\db\Migration;

class m260505_090000_add_performance_indexes extends Migration
{
    public function safeUp()
    {
        // appointment — найчастіші WHERE
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointment_user_status
                ON appointment(user_id, status)
        ");
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointment_specialist_date_status
                ON appointment(specialist_id, appointment_date, status)
        ");
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_appointment_date
                ON appointment(appointment_date)
        ");

        // розклад і блокування
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_specialist_schedule_specialist
                ON specialist_schedule(specialist_id)
        ");
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_specialist_day_block_specialist_date
                ON specialist_day_block(specialist_id, block_date)
        ");

        // платежі
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_payment_appointment
                ON payment(appointment_id)
        ");
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_payment_status
                ON payment(status)
        ");

        // сповіщення
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_notification_user_read
                ON notification(user_id, is_read)
        ");

        // відгуки (використовується в SpecialistController JOIN)
        $this->execute("
            CREATE INDEX IF NOT EXISTS idx_specialist_review_specialist
                ON specialist_review(specialist_id)
        ");
    }

    public function safeDown()
    {
        $indexes = [
            'idx_appointment_user_status',
            'idx_appointment_specialist_date_status',
            'idx_appointment_date',
            'idx_specialist_schedule_specialist',
            'idx_specialist_day_block_specialist_date',
            'idx_payment_appointment',
            'idx_payment_status',
            'idx_notification_user_read',
            'idx_specialist_review_specialist',
        ];
        foreach ($indexes as $idx) {
            $this->execute("DROP INDEX IF EXISTS {$idx}");
        }
    }
}
