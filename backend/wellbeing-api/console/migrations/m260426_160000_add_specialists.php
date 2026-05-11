<?php

use yii\db\Migration;

class m260426_160000_add_specialists extends Migration
{
    public function up()
    {
        // Specialist table
        $this->createTable('{{%specialist}}', [
            'id'               => $this->primaryKey(),
            'name'             => $this->string(100)->notNull(),
            'type'             => $this->string(50)->notNull(),
            'bio'              => $this->text(),
            'experience_years' => $this->integer()->defaultValue(0),
            'rating'           => $this->decimal(3, 1)->defaultValue(5.0),
            'categories'       => $this->text(), // comma-separated
            'avatar_initials'  => $this->string(4),
            'price'            => $this->decimal(10, 2)->defaultValue(0),
            'is_active'        => $this->boolean()->defaultValue(true),
            'created_at'       => $this->integer(),
            'updated_at'       => $this->integer(),
        ]);

        // Specialist schedule: day_of_week (0=Sun..6=Sat) + time slot
        $this->createTable('{{%specialist_schedule}}', [
            'id'            => $this->primaryKey(),
            'specialist_id' => $this->integer()->notNull(),
            'day_of_week'   => $this->smallInteger()->notNull(), // 0..6
            'time_slot'     => $this->string(5)->notNull(),      // HH:MM
        ]);

        $this->addForeignKey('fk_schedule_specialist', '{{%specialist_schedule}}', 'specialist_id', '{{%specialist}}', 'id', 'CASCADE');
        $this->createIndex('idx_schedule_specialist', '{{%specialist_schedule}}', 'specialist_id');

        // Link appointments to specialists (nullable — legacy rows have no specialist record)
        $this->addColumn('{{%appointment}}', 'specialist_id', $this->integer()->null()->after('user_id'));
        $this->addForeignKey('fk_appointment_specialist', '{{%appointment}}', 'specialist_id', '{{%specialist}}', 'id', 'SET NULL');

        // Seed data removed — specialists/schedules/appointments are managed via admin panel.
    }

    public function down()
    {
        $this->delete('{{%appointment}}', ['user_id' => 1]);
        $this->dropForeignKey('fk_appointment_specialist', '{{%appointment}}');
        $this->dropColumn('{{%appointment}}', 'specialist_id');
        $this->dropForeignKey('fk_schedule_specialist', '{{%specialist_schedule}}');
        $this->dropTable('{{%specialist_schedule}}');
        $this->dropTable('{{%specialist}}');
    }
}
