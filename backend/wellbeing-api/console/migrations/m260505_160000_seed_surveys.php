<?php

use yii\db\Migration;

class m260505_160000_seed_surveys extends Migration
{
    public function safeUp()
    {
        // No-op: survey seed data removed — surveys are managed via admin panel on production.
    }

    public function safeDown()
    {
        $this->db->createCommand(
            "DELETE FROM survey WHERE title IN ('PHQ-9 — Опитувальник стану здоров\'я', 'MHAI — Опитувальник оцінки психічного здоров\'я')"
        )->execute();
    }
}
