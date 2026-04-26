<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Questionnaire model (анкета PHQ-9)
 *
 * @property int $id
 * @property int $user_id
 * @property string $mood_emoji (😊, 😐, 😢, etc)
 * @property int $phq9_score (0-27)
 * @property array $phq9_answers (JSON with 9 answers)
 * @property string $notes
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class Questionnaire extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%questionnaire}}';
    }

    public function behaviors()
    {
        return [
            TimestampBehavior::class,
        ];
    }

    public function rules()
    {
        return [
            [['user_id'], 'required'],
            [['user_id'], 'integer'],
            [['mood_emoji'], 'string', 'max' => 10],
            [['phq9_score'], 'integer', 'min' => 0, 'max' => 27],
            [['phq9_answers', 'notes'], 'string'],
        ];
    }

    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'user_id']);
    }

    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'user_id' => 'Користувач',
            'mood_emoji' => 'Емодзі настрою',
            'phq9_score' => 'PHQ-9 Сума',
            'phq9_answers' => 'PHQ-9 Відповіді',
            'notes' => 'Примітки',
        ];
    }

    public function getMoodInterpretation()
    {
        if ($this->phq9_score <= 4) return 'Мінімальна депресія';
        if ($this->phq9_score <= 9) return 'Легка депресія';
        if ($this->phq9_score <= 14) return 'Помірна депресія';
        if ($this->phq9_score <= 19) return 'Середньо-важка депресія';
        return 'Важка депресія';
    }
}
