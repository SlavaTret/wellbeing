<?php

use yii\db\Migration;

class m260506_140000_add_creatio_contact_id_to_user extends Migration
{
    public function up()
    {
        $this->addColumn('user', 'creatio_contact_id', $this->string(36)->null()->defaultValue(null));
        $this->createIndex('idx_user_creatio_contact_id', 'user', 'creatio_contact_id');
    }

    public function down()
    {
        $this->dropIndex('idx_user_creatio_contact_id', 'user');
        $this->dropColumn('user', 'creatio_contact_id');
    }
}
