<?php

use yii\db\Migration;

class m260511_000000_add_company_columns_idempotent extends Migration
{
    public function safeUp()
    {
        $table = $this->db->getTableSchema('{{%company}}');
        if ($table && !isset($table->columns['free_sessions_per_user'])) {
            $this->addColumn('{{%company}}', 'free_sessions_per_user', $this->integer()->notNull()->defaultValue(0));
        }
    }

    public function safeDown()
    {
        $table = $this->db->getTableSchema('{{%company}}');
        if ($table && isset($table->columns['free_sessions_per_user'])) {
            $this->dropColumn('{{%company}}', 'free_sessions_per_user');
        }
    }
}
