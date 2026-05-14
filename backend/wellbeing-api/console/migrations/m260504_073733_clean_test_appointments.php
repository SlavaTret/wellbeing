<?php

use yii\db\Migration;

class m260504_073733_clean_test_appointments extends Migration
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
