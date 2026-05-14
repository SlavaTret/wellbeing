<?php

use yii\db\Migration;

class m260506_130000_add_creatio_contact_id_to_specialist extends Migration
{
    public function up()
    {
        $this->addColumn('specialist', 'creatio_contact_id', $this->string(36)->null()->defaultValue(null));
        $this->createIndex('idx_specialist_creatio_contact_id', 'specialist', 'creatio_contact_id');
    }

    public function down()
    {
        $this->dropIndex('idx_specialist_creatio_contact_id', 'specialist');
        $this->dropColumn('specialist', 'creatio_contact_id');
    }
}
