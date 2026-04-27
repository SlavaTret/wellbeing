<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int    $id
 * @property string $name
 * @property string $type
 * @property string $bio
 * @property int    $experience_years
 * @property float  $rating
 * @property string $categories
 * @property string $avatar_initials
 * @property float  $price
 * @property bool   $is_active
 */
class Specialist extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%specialist}}';
    }

    public function behaviors()
    {
        return [TimestampBehavior::class];
    }

    public function rules()
    {
        return [
            [['name', 'type'], 'required'],
            [['name', 'type', 'avatar_initials'], 'string'],
            [['bio', 'categories'], 'string'],
            [['experience_years'], 'integer'],
            [['rating', 'price'], 'number'],
            [['is_active'], 'boolean'],
        ];
    }

    public function getSchedules()
    {
        return $this->hasMany(SpecialistSchedule::class, ['specialist_id' => 'id']);
    }

    public function getCategoriesArray(): array
    {
        return $this->categories ? array_map('trim', explode(',', $this->categories)) : [];
    }

    /**
     * Returns available dates+slots for next $days days (Mon-Fri only).
     */
    public function getAvailableSlots(int $days = 14): array
    {
        $schedules = $this->schedules;
        if (!$schedules) {
            return [];
        }

        // Group time slots by day_of_week
        $byDay = [];
        foreach ($schedules as $s) {
            $byDay[$s->day_of_week][] = $s->time_slot;
        }

        $result = [];
        $today  = new \DateTime('today');
        $end    = (clone $today)->modify("+{$days} days");
        $d      = clone $today;

        while ($d <= $end) {
            $dow = (int)$d->format('w'); // 0=Sun
            if (isset($byDay[$dow])) {
                $dateStr = $d->format('Y-m-d');
                $result[] = [
                    'date'  => $dateStr,
                    'slots' => $byDay[$dow],
                ];
            }
            $d->modify('+1 day');
        }

        return $result;
    }
}
