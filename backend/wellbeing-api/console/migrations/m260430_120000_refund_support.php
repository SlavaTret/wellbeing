<?php

use yii\db\Migration;

class m260430_120000_refund_support extends Migration
{
    public function safeUp()
    {
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS refund_status VARCHAR(20) DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS refunded_at INTEGER DEFAULT NULL");
        $this->execute("ALTER TABLE payment ADD COLUMN IF NOT EXISTS refund_amount FLOAT DEFAULT NULL");
    }

    public function safeDown()
    {
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS refund_amount");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS refunded_at");
        $this->execute("ALTER TABLE payment DROP COLUMN IF EXISTS refund_status");
    }
}
