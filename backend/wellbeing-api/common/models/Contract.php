<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int         $id
 * @property int         $company_id
 * @property string|null $creatio_contract_id
 * @property string      $name
 * @property string      $start_date   YYYY-MM-DD
 * @property string      $end_date     YYYY-MM-DD
 * @property float       $session_price
 * @property int         $free_sessions_per_employee
 * @property bool        $is_active
 * @property string|null $synced_at
 * @property int         $created_at
 * @property int         $updated_at
 */
class Contract extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%contract}}';
    }

    public function behaviors(): array
    {
        return [TimestampBehavior::class];
    }

    public function rules(): array
    {
        return [
            [['company_id', 'start_date', 'end_date'], 'required'],
            [['company_id', 'free_sessions_per_employee'], 'integer'],
            [['session_price'], 'number'],
            [['is_active'], 'boolean'],
            [['start_date', 'end_date', 'synced_at'], 'safe'],
            [['name'], 'string', 'max' => 255],
            [['creatio_contract_id'], 'string', 'max' => 36],
        ];
    }

    /**
     * Find the contract active on a given date for a company.
     * When multiple contracts overlap the date, prefer the one with the later start_date.
     */
    public static function findForDate(int $companyId, string $date): ?self
    {
        return self::find()
            ->where(['company_id' => $companyId, 'is_active' => true])
            ->andWhere(['<=', 'start_date', $date])
            ->andWhere(['>=', 'end_date', $date])
            ->orderBy(['start_date' => SORT_DESC])
            ->one();
    }

    public function getCompany(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Company::class, ['id' => 'company_id']);
    }
}
