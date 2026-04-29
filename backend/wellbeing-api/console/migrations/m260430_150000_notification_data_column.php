<?php

use yii\db\Migration;

class m260430_150000_notification_data_column extends Migration
{
    public function safeUp()
    {
        $this->execute("ALTER TABLE notification ADD COLUMN IF NOT EXISTS data JSONB DEFAULT NULL");

        // Widen the type constraint to include all new notification types
        $this->execute("
            ALTER TABLE notification
            DROP CONSTRAINT IF EXISTS notification_type_check
        ");
    }

    public function safeDown()
    {
        $this->execute("ALTER TABLE notification DROP COLUMN IF EXISTS data");
    }
}
