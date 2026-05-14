<?php

use yii\db\Migration;

class m260427_140000_seed_payments extends Migration
{
    public function safeUp()
    {
        // No-op: seed data removed. Production DB already has real payment records.
    }

    public function safeDown()
    {
        return false;
    }
}
