<?php

use yii\db\Migration;

class m260506_160000_add_communication_method_to_appointment extends Migration
{
    public function up()
    {
        $this->addColumn('appointment', 'communication_method', $this->string(20)->null()->defaultValue(null));
    }

    public function down()
    {
        $this->dropColumn('appointment', 'communication_method');
    }
}
