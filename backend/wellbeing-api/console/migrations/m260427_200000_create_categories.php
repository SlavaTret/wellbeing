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

        // Seed from existing specialist.categories CSV strings
        $db = $this->db;
        $rows = $db->createCommand("SELECT categories FROM specialist WHERE categories IS NOT NULL AND categories <> ''")->queryAll();

        $names = [];
        foreach ($rows as $row) {
            foreach (explode(',', $row['categories']) as $cat) {
                $cat = trim($cat);
                if ($cat !== '') $names[$cat] = true;
            }
        }

        $now = time();
        foreach (array_keys($names) as $name) {
            $db->createCommand()->insert('{{%category}}', [
                'name'       => $name,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }
    }

    public function safeDown()
    {
        $this->dropTable('{{%category}}');
    }
}
