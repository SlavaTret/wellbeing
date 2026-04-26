<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Document model (документи)
 *
 * @property int $id
 * @property int $user_id
 * @property string $document_name
 * @property string $file_url
 * @property string $file_type (pdf, jpg, png, etc)
 * @property int $file_size
 * @property int $created_at
 * @property int $updated_at
 *
 * @property User $user
 */
class Document extends ActiveRecord
{
    const ALLOWED_TYPES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];

    public static function tableName()
    {
        return '{{%document}}';
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
            [['user_id', 'document_name', 'file_url', 'file_type'], 'required'],
            [['user_id', 'file_size'], 'integer'],
            [['document_name', 'file_url', 'file_type'], 'string'],
            [['file_type'], 'in', 'range' => self::ALLOWED_TYPES],
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
            'document_name' => 'Назва документу',
            'file_url' => 'URL файлу',
            'file_type' => 'Тип файлу',
            'file_size' => 'Розмір файлу',
        ];
    }
}
