<?php

use yii\db\Migration;

class m260429_061759_check_payment_cols extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

    }

    /**
     * {@inheritdoc}
     */
    public function safeDown()
    {
        echo "m260429_061759_check_payment_cols cannot be reverted.\n";

        return false;
    }

    /*
    // Use up()/down() to run migration code without a transaction.
    public function up()
    {

    }

    public function down()
    {
        echo "m260429_061759_check_payment_cols cannot be reverted.\n";

        return false;
    }
    */
}
