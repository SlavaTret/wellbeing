<?php

namespace common\models;

use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property int    $specialist_id
 * @property int    $day_of_week
 * @property string $time_slot
 */
class SpecialistSchedule extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%specialist_schedule}}';
    }

    public function getSpecialist()
    {
        return $this->hasOne(Specialist::class, ['id' => 'specialist_id']);
    }
}
