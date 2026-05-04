<?php

use yii\db\Migration;

class m260504_073733_clean_test_appointments extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {
        $this->execute('DELETE FROM payment');
        $this->execute('DELETE FROM appointment');
    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m260504_073733_clean_test_appointments cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260504_073733_clean_test_appointments cannot be reverted.\n";

        return false;
    }
    */
}
