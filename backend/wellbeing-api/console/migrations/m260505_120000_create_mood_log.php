<?php

use yii\db\Migration;

class m260505_120000_create_mood_log extends Migration
{
    public function safeUp()
    {
        $this->createTable('mood_log', [
            'id'         => $this->primaryKey(),
            'user_id'    => $this->integer()->notNull(),
            'mood'       => $this->smallInteger()->notNull(),
            'note'       => $this->text()->null(),
            'logged_at'  => $this->date()->notNull()->defaultExpression('CURRENT_DATE'),
            'created_at' => $this->timestamp()->notNull()->defaultExpression('NOW()'),
        ]);

        $this->addForeignKey(
            'fk_mood_log_user',
            'mood_log', 'user_id',
            'user', 'id',
            'CASCADE'
        );

        $this->createIndex('idx_mood_log_user_date', 'mood_log', ['user_id', 'logged_at']);

        // One entry per user per day
        $this->execute('ALTER TABLE mood_log ADD CONSTRAINT uq_mood_log_user_day UNIQUE (user_id, logged_at)');

        // Mood value 1-5
        $this->execute('ALTER TABLE mood_log ADD CONSTRAINT chk_mood_range CHECK (mood BETWEEN 1 AND 5)');
    }

    public function safeDown()
    {
        $this->dropTable('mood_log');
    }
}
