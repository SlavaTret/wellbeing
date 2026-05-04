<?php

use yii\db\Migration;

class m260504_110000_specialist_avatar_and_specializations extends Migration
{
    public function up()
    {
        // 1. Avatar URL for specialists
        $this->execute("ALTER TABLE specialist ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(512) DEFAULT NULL");

        // 2. Specialization table
        $this->execute("
            CREATE TABLE IF NOT EXISTS specialization (
                id         SERIAL PRIMARY KEY,
                name       VARCHAR(100) NOT NULL,
                key        VARCHAR(50)  NOT NULL UNIQUE,
                is_active  BOOLEAN      NOT NULL DEFAULT TRUE,
                sort_order INT          NOT NULL DEFAULT 0,
                created_at INT          NOT NULL DEFAULT 0,
                updated_at INT          NOT NULL DEFAULT 0
            )
        ");

        $now = time();
        $this->execute("
            INSERT INTO specialization (name, key, is_active, sort_order, created_at, updated_at) VALUES
                ('Психолог',      'psychologist', true, 1, $now, $now),
                ('Психотерапевт', 'therapist',    true, 2, $now, $now),
                ('Коуч',          'coach',        true, 3, $now, $now)
            ON CONFLICT (key) DO NOTHING
        ");
    }

    public function down()
    {
        $this->execute("ALTER TABLE specialist DROP COLUMN IF EXISTS avatar_url");
        $this->execute("DROP TABLE IF EXISTS specialization");
    }
}
