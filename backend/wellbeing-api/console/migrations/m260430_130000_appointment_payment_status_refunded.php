<?php

use yii\db\Migration;

class m260430_130000_appointment_payment_status_refunded extends Migration
{
    public function up()
    {
        $this->execute("
            ALTER TABLE appointment
            DROP CONSTRAINT IF EXISTS appointment_payment_status_check
        ");
        $this->execute("
            ALTER TABLE appointment
            ADD CONSTRAINT appointment_payment_status_check
            CHECK (payment_status IN ('paid','unpaid','pending','not_required','failed','refunded'))
        ");
    }

    public function down()
    {
        $this->execute("
            ALTER TABLE appointment
            DROP CONSTRAINT IF EXISTS appointment_payment_status_check
        ");
        $this->execute("
            ALTER TABLE appointment
            ADD CONSTRAINT appointment_payment_status_check
            CHECK (payment_status IN ('paid','unpaid','pending','not_required','failed'))
        ");
    }
}
