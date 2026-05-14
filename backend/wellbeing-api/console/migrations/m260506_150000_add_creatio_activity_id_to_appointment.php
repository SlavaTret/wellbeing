<?php

use yii\db\Migration;

class m260506_150000_add_creatio_activity_id_to_appointment extends Migration
{
    public function up()
    {
        $this->addColumn('appointment', 'creatio_activity_id', $this->string(36)->null()->defaultValue(null));
        $this->createIndex('idx_appointment_creatio_activity_id', 'appointment', 'creatio_activity_id');
    }

    public function down()
    {
        $this->dropIndex('idx_appointment_creatio_activity_id', 'appointment');
        $this->dropColumn('appointment', 'creatio_activity_id');
    }
}
