<?php

use yii\db\Migration;

class m260430_100000_payment_gateway_support extends Migration
{
    public function safeUp()
    {
        // Payment table — gateway columns
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS gateway VARCHAR(20) DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS gateway_order_id VARCHAR(255) DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS gateway_payment_id VARCHAR(255) DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS description TEXT DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS raw_response JSONB DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS paid_at INTEGER DEFAULT NULL");

        // Appointment table — extend payment_status to include 'pending'
        $this->execute("
            ALTER TABLE appointment
            DROP CONSTRAINT IF EXISTS appointment_payment_status_check
        ");
        $this->execute("
            ALTER TABLE appointment
            ADD CONSTRAINT appointment_payment_status_check
            CHECK (payment_status IN ('paid','unpaid','pending','not_required','failed'))
        ");

        // Index for gateway_order_id lookups
        $this->execute("CREATE INDEX IF NOT EXISTS idx_payment_gateway_order ON payment(gateway_order_id) WHERE gateway_order_id IS NOT NULL");
    }

    public function safeDown()
    {
        $this->execute("DROP INDEX IF EXISTS idx_payment_gateway_order");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS paid_at");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS raw_response");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS description");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS gateway_payment_id");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS gateway_order_id");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS gateway");
    }
}
