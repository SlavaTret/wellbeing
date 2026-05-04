<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property string $key
 * @property bool   $is_active
 * @property int    $sort_order
 */
class Specialization extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%specialization}}';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['name', 'key'], 'required'],
            ['name', 'string', 'max' => 100],
            ['key',  'string', 'max' => 50],
            ['key',  'match', 'pattern' => '/^[a-z0-9_-]+$/', 'message' => 'Ключ може містити лише малі літери, цифри, _ та -'],
            ['key',  'unique'],
            ['is_active',  'boolean'],
            ['sort_order', 'integer'],
        ];
    }
}
