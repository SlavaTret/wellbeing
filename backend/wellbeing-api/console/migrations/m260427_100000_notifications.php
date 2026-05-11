<?php

use yii\db\Migration;

class m260427_100000_notifications extends Migration
{
    public function safeUp()
    {
        // notification_setting (one row per user)
        $this->createTable('{{%notification_setting}}', [
            'id'                => $this->primaryKey(),
            'user_id'           => $this->integer()->notNull()->unique(),
            'email_enabled'     => $this->boolean()->notNull()->defaultValue(true),
            'calendar_enabled'  => $this->boolean()->notNull()->defaultValue(true),
            'sms_enabled'       => $this->boolean()->notNull()->defaultValue(false),
            'reminders_enabled' => $this->boolean()->notNull()->defaultValue(true),
            'created_at'        => $this->integer()->notNull(),
            'updated_at'        => $this->integer()->notNull(),
        ]);

        $this->addForeignKey('fk_notif_setting_user', '{{%notification_setting}}', 'user_id', '{{%user}}', 'id', 'CASCADE');

        // Seed data removed — notification_setting rows are created on user registration.
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_notif_setting_user', '{{%notification_setting}}');
        $this->dropTable('{{%notification_setting}}');
    }
}
