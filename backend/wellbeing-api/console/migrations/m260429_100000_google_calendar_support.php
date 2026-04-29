<?php

use yii\db\Migration;

class m260429_100000_google_calendar_support extends Migration
{
    public function safeUp()
    {
        // Google OAuth credentials & future merchant settings
        $this->execute("
            CREATE TABLE IF NOT EXISTS app_settings (
                key        VARCHAR(100) PRIMARY KEY,
                value      TEXT,
                updated_at INTEGER DEFAULT 0
            )
        ");

        // Google tokens per user
        $this->execute("
            CREATE TABLE IF NOT EXISTS user_google_token (
                user_id       INTEGER PRIMARY KEY REFERENCES \"user\"(id) ON DELETE CASCADE,
                access_token  TEXT,
                refresh_token TEXT,
                expires_at    INTEGER DEFAULT 0,
                google_email  VARCHAR(255),
                created_at    INTEGER DEFAULT 0,
                updated_at    INTEGER DEFAULT 0
            )
        ");

        // Google Calendar event references on appointment
        $this->execute("ALTER TABLE appointment ADD COLUMN IF NOT EXISTS google_event_id VARCHAR(255)");
        $this->execute("ALTER TABLE appointment ADD COLUMN IF NOT EXISTS google_meet_link VARCHAR(500)");

        // Specialist email
        $this->execute("ALTER TABLE specialist ADD COLUMN IF NOT EXISTS email VARCHAR(255)");
    }

    public function safeDown()
    {
        $this->execute("ALTER TABLE appointment DROP COLUMN IF EXISTS google_meet_link");
        $this->execute("ALTER TABLE appointment DROP COLUMN IF EXISTS google_event_id");
        $this->execute("ALTER TABLE specialist   DROP COLUMN IF EXISTS email");
        $this->dropTable('user_google_token');
        $this->dropTable('app_settings');
    }
}
