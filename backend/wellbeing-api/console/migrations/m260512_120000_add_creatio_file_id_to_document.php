<?php

use yii\db\Migration;

class m260512_120000_add_creatio_file_id_to_document extends Migration
{
    public function safeUp()
    {
        $table = $this->db->getTableSchema('{{%document}}');
        if ($table && !isset($table->columns['creatio_file_id'])) {
            $this->addColumn('{{%document}}', 'creatio_file_id', $this->string(36)->null()->defaultValue(null));
        }
    }

    public function safeDown()
    {
        $table = $this->db->getTableSchema('{{%document}}');
        if ($table && isset($table->columns['creatio_file_id'])) {
            $this->dropColumn('{{%document}}', 'creatio_file_id');
        }
    }
}
