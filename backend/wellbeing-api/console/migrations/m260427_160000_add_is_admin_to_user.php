<?php

use yii\db\Migration;

class m260427_160000_add_is_admin_to_user extends Migration
{
    public function safeUp()
    {
        $this->addColumn('{{%user}}', 'is_admin', $this->boolean()->defaultValue(false)->notNull());
    }

    public function safeDown()
    {
        $this->dropColumn('{{%user}}', 'is_admin');
    }
}
