<?php

namespace common\models;

use yii\behaviors\TimestampBehavior;
use yii\db\ActiveRecord;

/**
 * @property int         $id
 * @property string      $name
 * @property string      $type
 * @property string      $bio
 * @property int         $experience_years
 * @property float       $rating
 * @property string      $categories
 * @property string      $avatar_initials
 * @property float       $price
 * @property bool        $is_active
 * @property string|null $email
 * @property string|null $avatar_url
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
            [['email'], 'email'],
            [['email', 'avatar_url'], 'string', 'max' => 512],
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

        $byDay = [];
        foreach ($schedules as $s) {
            $byDay[$s->day_of_week][] = $s->time_slot;
        }

        $today   = new \DateTime('today');
        $end     = (clone $today)->modify("+{$days} days");

        // Blocked dates in range
        $blockedDates = \Yii::$app->db->createCommand(
            'SELECT block_date FROM specialist_day_block WHERE specialist_id = :id AND block_date >= :from AND block_date <= :to',
            [':id' => $this->id, ':from' => $today->format('Y-m-d'), ':to' => $end->format('Y-m-d')]
        )->queryColumn();
        $blockedSet = array_flip($blockedDates);

        // Already booked slots in range
        $bookedRows = \Yii::$app->db->createCommand(
            "SELECT appointment_date, appointment_time FROM appointment
             WHERE specialist_id = :id AND appointment_date >= :from AND appointment_date <= :to
             AND status NOT IN ('cancelled')",
            [':id' => $this->id, ':from' => $today->format('Y-m-d'), ':to' => $end->format('Y-m-d')]
        )->queryAll();
        $bookedSet = [];
        foreach ($bookedRows as $b) {
            $bookedSet[$b['appointment_date'] . '_' . $b['appointment_time']] = true;
        }

        $result = [];
        $d = clone $today;
        while ($d <= $end) {
            $dow     = (int)$d->format('w');
            $dateStr = $d->format('Y-m-d');

            if (isset($byDay[$dow]) && !isset($blockedSet[$dateStr])) {
                $free = array_filter($byDay[$dow], fn($t) => !isset($bookedSet[$dateStr . '_' . $t]));
                if ($free) {
                    $result[] = ['date' => $dateStr, 'slots' => array_values($free)];
                }
            }
            $d->modify('+1 day');
        }

        return $result;
    }
}
