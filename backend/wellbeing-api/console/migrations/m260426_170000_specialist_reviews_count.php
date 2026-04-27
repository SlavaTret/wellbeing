<?php

use yii\db\Migration;

class m260426_170000_specialist_reviews_count extends Migration
{
    public function up()
    {
        $this->addColumn('{{%specialist}}', 'reviews_count', $this->integer()->defaultValue(0)->after('rating'));

        $this->update('{{%specialist}}', ['reviews_count' => 47], ['name' => 'Марія Іваненко']);
        $this->update('{{%specialist}}', ['reviews_count' => 23], ['name' => 'Дмитро Сорока']);
        $this->update('{{%specialist}}', ['reviews_count' => 61], ['name' => 'Аліна Бойко']);
        $this->update('{{%specialist}}', ['reviews_count' => 18], ['name' => 'Ірина Василенко']);
    }

    public function down()
    {
        $this->dropColumn('{{%specialist}}', 'reviews_count');
    }
}
