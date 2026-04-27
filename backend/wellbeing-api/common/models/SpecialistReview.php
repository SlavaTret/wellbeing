<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $specialist_id
 * @property int    $user_id
 * @property int    $appointment_id
 * @property int    $rating
 * @property string $comment
 */
class SpecialistReview extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%specialist_review}}';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['specialist_id', 'user_id', 'rating'], 'required'],
            [['specialist_id', 'user_id', 'appointment_id'], 'integer'],
            [['rating'], 'integer', 'min' => 1, 'max' => 5],
            [['comment'], 'string'],
        ];
    }

    public function getSpecialist()
    {
        return $this->hasOne(Specialist::class, ['id' => 'specialist_id']);
    }
}
