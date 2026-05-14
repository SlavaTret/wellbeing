<?php

use yii\db\Migration;

class m260505_200000_fix_survey_options_jsonb extends Migration
{
    public function safeUp()
    {
        // Yii2 JSON-encodes PHP strings before inserting into JSONB columns,
        // causing the array to be stored as a JSONB string value instead of a JSONB array.
        // This converts: jsonb string "\"[...]\""  →  jsonb array [...]
        $this->db->createCommand("
            UPDATE survey_question
            SET options = (options #>> '{}')::jsonb
            WHERE jsonb_typeof(options) = 'string'
        ")->execute();

        // Same fix for survey_response answers column
        $this->db->createCommand("
            UPDATE survey_response
            SET answers = (answers #>> '{}')::jsonb
            WHERE jsonb_typeof(answers) = 'string'
        ")->execute();
    }

    public function safeDown()
    {
        // Not reversible — data is already correctly stored
    }
}
