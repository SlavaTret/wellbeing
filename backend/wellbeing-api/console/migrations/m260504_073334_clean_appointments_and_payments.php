<?php

use yii\db\Migration;

class m260504_073334_clean_appointments_and_payments extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute('DELETE FROM payment');
        $this->execute('DELETE FROM appointment');
        $this->execute('DELETE FROM specialist_review');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m260504_073334_clean_appointments_and_payments cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260504_073334_clean_appointments_and_payments cannot be reverted.\n";

        return false;
    }
    */
}
