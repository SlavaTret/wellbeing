<?php

use yii\db\Migration;

class m260426_180000_specialist_reviews extends Migration
{
    public function up()
    {
        $this->createTable('{{%specialist_review}}', [
            'id'             => $this->primaryKey(),
            'specialist_id'  => $this->integer()->notNull(),
            'user_id'        => $this->integer()->notNull(),
            'appointment_id' => $this->integer()->null(),
            'rating'         => $this->smallInteger()->notNull(),
            'comment'        => $this->text(),
            'created_at'     => $this->integer(),
            'updated_at'     => $this->integer(),
        ]);

        $this->addForeignKey('fk_sr_specialist', '{{%specialist_review}}', 'specialist_id', '{{%specialist}}', 'id', 'CASCADE');
        $this->addForeignKey('fk_sr_user',       '{{%specialist_review}}', 'user_id',       '{{%user}}',       'id', 'CASCADE');
        $this->addForeignKey('fk_sr_appointment','{{%specialist_review}}', 'appointment_id','{{%appointment}}','id', 'SET NULL');

        $this->createIndex('idx_sr_specialist', '{{%specialist_review}}', 'specialist_id');
        $this->createIndex('idx_sr_user',       '{{%specialist_review}}', 'user_id');
        // one review per appointment
        $this->createIndex('uniq_sr_appointment', '{{%specialist_review}}', 'appointment_id', true);
    }

    public function down()
    {
        $this->dropTable('{{%specialist_review}}');
    }
}
