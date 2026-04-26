<?php

use yii\db\Migration;

/**
 * Migration for creating wellbeing database tables
 */
class m260426_144000_init_db extends Migration
{
    public function up()
    {
        // User table (extend existing)
        $this->addColumn('{{%user}}', 'first_name', $this->string(50));
        $this->addColumn('{{%user}}', 'last_name', $this->string(50));
        $this->addColumn('{{%user}}', 'patronymic', $this->string(50));
        $this->addColumn('{{%user}}', 'phone', $this->string(20));
        $this->addColumn('{{%user}}', 'company', $this->string(100));
        $this->addColumn('{{%user}}', 'avatar_url', $this->string(500));
        $this->addColumn('{{%user}}', 'accepted_terms', $this->boolean()->defaultValue(false));

        // Appointment table
        $this->createTable('{{%appointment}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'specialist_name' => $this->string(100)->notNull(),
            'specialist_type' => $this->string(50)->notNull(),
            'appointment_date' => $this->date()->notNull(),
            'appointment_time' => $this->string(8)->notNull(), // HH:MM format
            'status' => $this->string(20)->defaultValue('pending'),
            'payment_status' => $this->string(20)->defaultValue('unpaid'),
            'notes' => $this->text(),
            'price' => $this->decimal(10, 2)->defaultValue(0),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->addForeignKey('fk_appointment_user_id', '{{%appointment}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('idx_appointment_user_id', '{{%appointment}}', 'user_id');
        $this->createIndex('idx_appointment_date', '{{%appointment}}', 'appointment_date');

        // Document table
        $this->createTable('{{%document}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'document_name' => $this->string(255)->notNull(),
            'file_url' => $this->string(500)->notNull(),
            'file_type' => $this->string(20),
            'file_size' => $this->integer(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->addForeignKey('fk_document_user_id', '{{%document}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('idx_document_user_id', '{{%document}}', 'user_id');

        // Payment table
        $this->createTable('{{%payment}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'appointment_id' => $this->integer(),
            'amount' => $this->decimal(10, 2)->notNull(),
            'currency' => $this->string(3)->defaultValue('UAH'),
            'status' => $this->string(20)->defaultValue('pending'),
            'payment_method' => $this->string(50),
            'transaction_id' => $this->string(100),
            'notes' => $this->text(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->addForeignKey('fk_payment_user_id', '{{%payment}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_payment_appointment_id', '{{%payment}}', 'appointment_id', '{{%appointment}}', 'id', 'SET NULL');
        $this->createIndex('idx_payment_user_id', '{{%payment}}', 'user_id');
        $this->createIndex('idx_payment_appointment_id', '{{%payment}}', 'appointment_id');

        // Notification table
        $this->createTable('{{%notification}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'type' => $this->string(50)->notNull(),
            'title' => $this->string(255)->notNull(),
            'message' => $this->text()->notNull(),
            'is_read' => $this->boolean()->defaultValue(false),
            'notification_channels' => $this->string(100),
            'related_appointment_id' => $this->integer(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->addForeignKey('fk_notification_user_id', '{{%notification}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('idx_notification_user_id', '{{%notification}}', 'user_id');
        $this->createIndex('idx_notification_is_read', '{{%notification}}', 'is_read');

        // Questionnaire table
        $this->createTable('{{%questionnaire}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'mood_emoji' => $this->string(10),
            'phq9_score' => $this->integer()->defaultValue(0),
            'phq9_answers' => $this->json(),
            'notes' => $this->text(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->addForeignKey('fk_questionnaire_user_id', '{{%questionnaire}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('idx_questionnaire_user_id', '{{%questionnaire}}', 'user_id');

        // SupportTicket table
        $this->createTable('{{%support_ticket}}', [
            'id' => $this->primaryKey(),
            'user_id' => $this->integer()->notNull(),
            'title' => $this->string(255)->notNull(),
            'message' => $this->text()->notNull(),
            'priority' => $this->string(20)->defaultValue('medium'),
            'status' => $this->string(20)->defaultValue('open'),
            'contact_method' => $this->string(50),
            'response_message' => $this->text(),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->addForeignKey('fk_support_ticket_user_id', '{{%support_ticket}}', 'user_id', '{{%user}}', 'id', 'CASCADE');
        $this->createIndex('idx_support_ticket_user_id', '{{%support_ticket}}', 'user_id');
        $this->createIndex('idx_support_ticket_status', '{{%support_ticket}}', 'status');
    }

    public function down()
    {
        // Drop tables in reverse order
        $this->dropTable('{{%support_ticket}}');
        $this->dropTable('{{%questionnaire}}');
        $this->dropTable('{{%notification}}');
        $this->dropTable('{{%payment}}');
        $this->dropTable('{{%document}}');
        $this->dropTable('{{%appointment}}');

        // Drop user columns
        $this->dropColumn('{{%user}}', 'first_name');
        $this->dropColumn('{{%user}}', 'last_name');
        $this->dropColumn('{{%user}}', 'patronymic');
        $this->dropColumn('{{%user}}', 'phone');
        $this->dropColumn('{{%user}}', 'company');
        $this->dropColumn('{{%user}}', 'avatar_url');
        $this->dropColumn('{{%user}}', 'accepted_terms');
    }
}
