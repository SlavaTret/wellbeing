<?php

use yii\db\Migration;

class m260426_150000_add_companies extends Migration
{
    public function up()
    {
        $this->createTable('{{%company}}', [
            'id' => $this->primaryKey(),
            'code' => $this->string(50)->notNull()->unique(),
            'name' => $this->string(100)->notNull(),
            'logo_url' => $this->string(500),
            'primary_color' => $this->string(7)->defaultValue('#2DB928'),
            'secondary_color' => $this->string(7)->defaultValue('#1E9020'),
            'accent_color' => $this->string(7)->defaultValue('#E8F5E9'),
            'is_active' => $this->boolean()->defaultValue(true),
            'created_at' => $this->integer(),
            'updated_at' => $this->integer(),
        ]);

        $this->createIndex('idx_company_code', '{{%company}}', 'code');
        $this->createIndex('idx_company_active', '{{%company}}', 'is_active');

        // Add company_id to user table (alongside existing string `company` column for legacy display)
        $this->addColumn('{{%user}}', 'company_id', $this->integer());
        $this->addForeignKey('fk_user_company_id', '{{%user}}', 'company_id', '{{%company}}', 'id', 'SET NULL');
        $this->createIndex('idx_user_company_id', '{{%user}}', 'company_id');
    }

    public function down()
    {
        $this->dropForeignKey('fk_user_company_id', '{{%user}}');
        $this->dropColumn('{{%user}}', 'company_id');
        $this->dropTable('{{%company}}');
    }
}
