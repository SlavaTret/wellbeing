<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property string $status  (active | inactive)
 */
class Category extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%category}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['name'], 'required'],
            [['name'], 'string', 'max' => 120],
            [['name'], 'unique'],
            [['status'], 'in', 'range' => ['active', 'inactive']],
        ];
    }
}
