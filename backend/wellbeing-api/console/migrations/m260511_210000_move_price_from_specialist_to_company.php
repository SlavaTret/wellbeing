<?php

use yii\db\Migration;

class m260511_210000_move_price_from_specialist_to_company extends Migration
{
    public function safeUp()
    {
        // Add session_price to company (idempotent)
        $table = $this->db->getTableSchema('{{%company}}');
        if ($table && !isset($table->columns['session_price'])) {
            $this->addColumn('{{%company}}', 'session_price', $this->decimal(10, 2)->null()->defaultValue(null));
        }

        // Drop price from specialist (idempotent)
        $specTable = $this->db->getTableSchema('{{%specialist}}');
        if ($specTable && isset($specTable->columns['price'])) {
            $this->dropColumn('{{%specialist}}', 'price');
        }
    }

    public function safeDown()
    {
        $table = $this->db->getTableSchema('{{%company}}');
        if ($table && isset($table->columns['session_price'])) {
            $this->dropColumn('{{%company}}', 'session_price');
        }

        $specTable = $this->db->getTableSchema('{{%specialist}}');
        if ($specTable && !isset($specTable->columns['price'])) {
            $this->addColumn('{{%specialist}}', 'price', $this->decimal(10, 2)->notNull()->defaultValue(0));
        }
    }
}
