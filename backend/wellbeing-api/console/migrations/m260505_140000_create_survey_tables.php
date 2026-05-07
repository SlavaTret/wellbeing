<?php
use yii\db\Migration;

class m260505_140000_create_survey_tables extends Migration
{
    public function safeUp()
    {
        $this->createTable('survey', [
            'id'          => $this->primaryKey(),
            'title'       => $this->string(255)->notNull(),
            'description' => $this->text()->null(),
            'is_active'   => $this->boolean()->defaultValue(false),
            'created_at'  => $this->timestamp()->notNull()->defaultExpression('NOW()'),
        ]);

        $this->createTable('survey_question', [
            'id'         => $this->primaryKey(),
            'survey_id'  => $this->integer()->notNull(),
            'question'   => $this->text()->notNull(),
            'sort_order' => $this->smallInteger()->defaultValue(0),
            'options'    => 'JSONB NOT NULL',
        ]);
        $this->addForeignKey('fk_sq_survey', 'survey_question', 'survey_id', 'survey', 'id', 'CASCADE');
        $this->createIndex('idx_survey_question_survey', 'survey_question', ['survey_id', 'sort_order']);

        $this->createTable('survey_response', [
            'id'           => $this->primaryKey(),
            'user_id'      => $this->integer()->notNull(),
            'survey_id'    => $this->integer()->notNull(),
            'answers'      => 'JSONB NOT NULL',
            'completed_at' => $this->timestamp()->notNull()->defaultExpression('NOW()'),
        ]);
        $this->addForeignKey('fk_sr_user',   'survey_response', 'user_id',   'user',   'id', 'CASCADE');
        $this->addForeignKey('fk_sr_survey', 'survey_response', 'survey_id', 'survey', 'id', 'CASCADE');
        $this->execute('ALTER TABLE survey_response ADD CONSTRAINT uq_survey_response_user UNIQUE (user_id, survey_id)');
        $this->createIndex('idx_survey_response', 'survey_response', ['survey_id', 'completed_at']);
    }

    public function safeDown()
    {
        $this->dropTable('survey_response');
        $this->dropTable('survey_question');
        $this->dropTable('survey');
    }
}
