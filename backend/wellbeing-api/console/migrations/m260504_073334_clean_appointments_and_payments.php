<?php

use yii\db\Migration;

class m260504_073334_clean_appointments_and_payments extends Migration
{
    public function safeUp()
    {
        // No-op: was a one-time dev cleanup. DELETE statements removed to protect real user data.
    }

    public function safeDown()
    {
        return false;
    }
}
