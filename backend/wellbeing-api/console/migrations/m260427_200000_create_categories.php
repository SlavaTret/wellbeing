<?php

use yii\db\Migration;

class m260427_200000_create_categories extends Migration
{
    public function safeUp()
    {
        $this->createTable('{{%category}}', [
            'id'         => $this->primaryKey(),
            'name'       => $this->string(120)->notNull()->unique(),
            'status'     => $this->string(20)->notNull()->defaultValue('active'),
            'created_at' => $this->integer()->notNull()->defaultValue(0),
            'updated_at' => $this->integer()->notNull()->defaultValue(0),
        ]);

        // Seed data removed — categories are managed via admin panel on production.
    }

    public function safeDown()
    {
        $this->dropTable('{{%category}}');
    }
}
