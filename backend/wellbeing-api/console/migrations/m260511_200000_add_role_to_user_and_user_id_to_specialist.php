<?php

use yii\db\Migration;

class m260511_200000_add_role_to_user_and_user_id_to_specialist extends Migration
{
    public function safeUp()
    {
        $userTable = $this->db->getTableSchema('{{%user}}');
        if ($userTable && !isset($userTable->columns['role'])) {
            $this->addColumn('{{%user}}', 'role', $this->string(20)->notNull()->defaultValue('user'));
        }

        $specTable = $this->db->getTableSchema('{{%specialist}}');
        if ($specTable && !isset($specTable->columns['user_id'])) {
            $this->addColumn('{{%specialist}}', 'user_id', $this->integer()->null());
            $this->createIndex('idx_specialist_user_id', '{{%specialist}}', 'user_id', true);
            $this->addForeignKey(
                'fk_specialist_user_id',
                '{{%specialist}}', 'user_id',
                '{{%user}}', 'id',
                'SET NULL', 'CASCADE'
            );
        }
    }

    public function safeDown()
    {
        $specTable = $this->db->getTableSchema('{{%specialist}}');
        if ($specTable && isset($specTable->columns['user_id'])) {
            $this->dropForeignKey('fk_specialist_user_id', '{{%specialist}}');
            $this->dropIndex('idx_specialist_user_id', '{{%specialist}}');
            $this->dropColumn('{{%specialist}}', 'user_id');
        }

        $userTable = $this->db->getTableSchema('{{%user}}');
        if ($userTable && isset($userTable->columns['role'])) {
            $this->dropColumn('{{%user}}', 'role');
        }
    }
}
