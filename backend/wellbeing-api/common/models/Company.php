<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * Company model — represents a partner company with its branding.
 *
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property string $logo_url
 * @property string $primary_color
 * @property string $secondary_color
 * @property string $accent_color
 * @property int        $free_sessions_per_user
 * @property float|null $session_price
 * @property bool       $is_active
 * @property int    $created_at
 * @property int    $updated_at
 * @property string|null $creatio_account_id  Creatio Account GUID for bidirectional sync
 */
class Company extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%company}}';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['code', 'name'], 'required'],
            [['code'], 'unique'],
            [['code', 'name'], 'string', 'max' => 100],
            [['logo_url'], 'string', 'max' => 500],
            [['primary_color', 'secondary_color', 'accent_color'], 'string', 'max' => 7],
            [['is_active'], 'boolean'],
            [['free_sessions_per_user'], 'integer'],
            [['session_price'], 'number'],
            [['creatio_account_id'], 'string', 'max' => 36],
        ];
    }

    public function toBrandingArray()
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'logo_url' => $this->logo_url,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
        ];
    }
}
