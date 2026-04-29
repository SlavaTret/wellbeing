<?php

use yii\db\Migration;

class m260429_114847_fix_appointment_12_payment_status extends Migration
{
    public function safeUp()
    {
        // Fix appointments that are cancelled but still show payment_status='paid'
        // because the payment was refunded but the appointment update failed (prior constraint violation).
        $this->execute("
            UPDATE appointment a
            SET payment_status = 'refunded'
            WHERE a.status = 'cancelled'
              AND a.payment_status = 'paid'
              AND EXISTS (
                  SELECT 1 FROM payment p
                  WHERE p.appointment_id = a.id
                    AND p.status = 'refunded'
              )
        ");
    }

    public function safeDown()
    {
        echo "m260429_114847_fix_appointment_12_payment_status cannot be reverted.\n";
        return false;
    }
}
