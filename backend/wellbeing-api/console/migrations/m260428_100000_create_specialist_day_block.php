<?php

use yii\db\Migration;

class m260428_100000_create_specialist_day_block extends Migration
{
    public function safeUp()
    {
        $this->createTable('specialist_day_block', [
            'id'            => $this->primaryKey(),
            'specialist_id' => $this->integer()->notNull(),
            'block_date'    => $this->date()->notNull(),
        ]);

        $this->createIndex('idx_sdb_specialist_date', 'specialist_day_block', ['specialist_id', 'block_date'], true);
        $this->addForeignKey('fk_sdb_specialist', 'specialist_day_block', 'specialist_id', 'specialist', 'id', 'CASCADE');
    }

    public function safeDown()
    {
        $this->dropTable('specialist_day_block');
    }
}
