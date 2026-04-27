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

        $now  = time();
        $users = (new \yii\db\Query())->select('id')->from('{{%user}}')->all();

        foreach ($users as $u) {
            $uid = $u['id'];

            // Default settings row
            $this->insert('{{%notification_setting}}', [
                'user_id'           => $uid,
                'email_enabled'     => true,
                'calendar_enabled'  => true,
                'sms_enabled'       => false,
                'reminders_enabled' => true,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // Seed notifications using existing schema (message, type)
            $this->batchInsert('{{%notification}}',
                ['user_id', 'type', 'title', 'message', 'is_read', 'created_at', 'updated_at'],
                [
                    [$uid, 'appointment_reminder', 'Нагадування про консультацію',
                        'Ваша консультація з Марією Іваненко завтра о 14:00',   false, $now - 7200,  $now - 7200],
                    [$uid, 'other', 'Запис підтверджено',
                        'Дмитро Сорока підтвердив ваш запис на 5 травня о 10:30', false, $now - 86400, $now - 86400],
                    [$uid, 'other', 'Консультацію завершено',
                        'Залиште відгук про консультацію від 15 квітня',          true,  $now - 172800,$now - 172800],
                ]
            );
        }
    }

    public function safeDown()
    {
        $this->dropForeignKey('fk_notif_setting_user', '{{%notification_setting}}');
        $this->dropTable('{{%notification_setting}}');
    }
}
