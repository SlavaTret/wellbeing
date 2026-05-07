<?php

use yii\db\Migration;

class m260506_120000_add_creatio_ids_to_company extends Migration
{
    public function up()
    {
        $this->addColumn('company', 'creatio_account_id', $this->string(36)->null()->defaultValue(null));
        $this->createIndex('idx_company_creatio_account_id', 'company', 'creatio_account_id');
    }

    public function down()
    {
        $this->dropIndex('idx_company_creatio_account_id', 'company');
        $this->dropColumn('company', 'creatio_account_id');
    }
}
