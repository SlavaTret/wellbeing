<?php

use yii\db\Migration;

class m260512_100000_create_contract_table extends Migration
{
    public function safeUp()
    {
        $table = $this->db->getTableSchema('{{%contract}}');
        if ($table) {
            return; // idempotent
        }

        $this->createTable('{{%contract}}', [
            'id'                          => $this->primaryKey(),
            'company_id'                  => $this->integer()->notNull(),
            'creatio_contract_id'         => $this->string(36)->null(),
            'name'                        => $this->string(255)->notNull()->defaultValue(''),
            'start_date'                  => $this->date()->notNull(),
            'end_date'                    => $this->date()->notNull(),
            'session_price'               => $this->decimal(10, 2)->notNull()->defaultValue(0),
            'free_sessions_per_employee'  => $this->integer()->notNull()->defaultValue(0),
            'is_active'                   => $this->boolean()->notNull()->defaultValue(true),
            'synced_at'                   => $this->timestamp()->null(),
            'created_at'                  => $this->integer()->notNull()->defaultValue(0),
            'updated_at'                  => $this->integer()->notNull()->defaultValue(0),
        ]);

        $this->addForeignKey('fk_contract_company', '{{%contract}}', 'company_id', '{{%company}}', 'id', 'CASCADE');
        $this->createIndex('idx_contract_company_id', '{{%contract}}', 'company_id');
        $this->createIndex('idx_contract_creatio_id', '{{%contract}}', 'creatio_contract_id', true);
        $this->createIndex('idx_contract_dates', '{{%contract}}', ['company_id', 'start_date', 'end_date']);
    }

    public function safeDown()
    {
        $this->dropTable('{{%contract}}');
    }
}
