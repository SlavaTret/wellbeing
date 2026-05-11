<?php

namespace api\modules\v1\controllers;

use common\models\Specialist;
use common\models\SpecialistSchedule;
use Yii;
use yii\rest\Controller;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class SpecialistPanelController extends Controller
{
    public $modelClass = 'common\models\User';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ];
        return $behaviors;
    }

    private function getMySpecialist(): Specialist
    {
        $user = Yii::$app->user->identity;
        if (!$user || $user->role !== 'specialist') {
            throw new ForbiddenHttpException('Доступ заборонено');
        }
        $specialist = Specialist::findOne(['user_id' => $user->id]);
        if (!$specialist) {
            throw new NotFoundHttpException('Профіль спеціаліста не знайдено');
        }
        return $specialist;
    }

    // ── Dashboard ────────────────────────────────────────────────────

    public function actionDashboard()
    {
        Yii::$app->response->format = 'json';
        $s  = $this->getMySpecialist();
        $db = Yii::$app->db;
        $today = date('Y-m-d');

        $row = $db->createCommand("
            SELECT
                (SELECT COUNT(*) FROM appointment WHERE specialist_id = :id)                                          AS total,
                (SELECT COUNT(*) FROM appointment WHERE specialist_id = :id AND appointment_date = :today)            AS today_count,
                (SELECT COUNT(*) FROM appointment WHERE specialist_id = :id AND appointment_date >= :today
                    AND status NOT IN ('cancelled'))                                                                   AS upcoming,
                (SELECT COUNT(*) FROM appointment WHERE specialist_id = :id AND status = 'pending')                   AS pending_count
        ", [':id' => $s->id, ':today' => $today])->queryOne();

        $recent = $db->createCommand("
            SELECT
                a.id, a.appointment_date, a.appointment_time, a.status, a.payment_status, a.notes,
                TRIM(COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')) AS user_name,
                u.email AS user_email, u.avatar_url
            FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            WHERE a.specialist_id = :id
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT 8
        ", [':id' => $s->id])->queryAll();

        return [
            'specialist' => [
                'id'              => $s->id,
                'name'            => $s->name,
                'type'            => $s->type,
                'avatar_url'      => $s->avatar_url,
                'avatar_initials' => $s->avatar_initials,
            ],
            'stats' => [
                'total'         => (int)$row['total'],
                'today'         => (int)$row['today_count'],
                'upcoming'      => (int)$row['upcoming'],
                'pending'       => (int)$row['pending_count'],
            ],
            'recent_appointments' => array_map(fn($r) => [
                'id'               => (int)$r['id'],
                'appointment_date' => $r['appointment_date'],
                'appointment_time' => $r['appointment_time'],
                'status'           => $r['status'],
                'payment_status'   => $r['payment_status'],
                'notes'            => $r['notes'],
                'user_name'        => $r['user_name'] ?: 'Невідомий',
                'user_email'       => $r['user_email'],
                'avatar_url'       => $r['avatar_url'],
            ], $recent),
        ];
    }

    // ── Appointments ─────────────────────────────────────────────────

    public function actionAppointments()
    {
        Yii::$app->response->format = 'json';
        $s      = $this->getMySpecialist();
        $db     = Yii::$app->db;
        $search = Yii::$app->request->get('search', '');
        $status = Yii::$app->request->get('status', '');
        $page   = max(1, (int)Yii::$app->request->get('page', 1));
        $limit  = 15;
        $offset = ($page - 1) * $limit;

        $where  = 'a.specialist_id = :sid';
        $params = [':sid' => $s->id];

        if ($status && $status !== 'all') {
            $where .= ' AND a.status = :status';
            $params[':status'] = $status;
        }
        if ($search) {
            $where .= " AND (u.first_name ILIKE :q OR u.last_name ILIKE :q OR u.email ILIKE :q)";
            $params[':q'] = '%' . $search . '%';
        }

        $total = (int)$db->createCommand("
            SELECT COUNT(*) FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            WHERE $where
        ", $params)->queryScalar();

        $rows = $db->createCommand("
            SELECT
                a.id, a.appointment_date, a.appointment_time,
                a.status, a.payment_status, a.notes, a.price,
                a.communication_method, a.created_at,
                TRIM(COALESCE(u.first_name,'') || ' ' || COALESCE(u.last_name,'')) AS user_name,
                u.email AS user_email, u.phone AS user_phone, u.avatar_url,
                c.name AS company_name
            FROM appointment a
            LEFT JOIN \"user\" u ON u.id = a.user_id
            LEFT JOIN company c ON c.id = u.company_id
            WHERE $where
            ORDER BY a.appointment_date DESC, a.appointment_time DESC
            LIMIT $limit OFFSET $offset
        ", $params)->queryAll();

        return [
            'items' => array_map(fn($r) => [
                'id'                 => (int)$r['id'],
                'appointment_date'   => $r['appointment_date'],
                'appointment_time'   => $r['appointment_time'],
                'status'             => $r['status'],
                'payment_status'     => $r['payment_status'],
                'notes'              => $r['notes'],
                'price'              => (float)$r['price'],
                'communication_method' => $r['communication_method'],
                'user_name'          => $r['user_name'] ?: 'Невідомий',
                'user_email'         => $r['user_email'],
                'user_phone'         => $r['user_phone'],
                'avatar_url'         => $r['avatar_url'],
                'company_name'       => $r['company_name'],
                'created_at'         => $r['created_at'],
            ], $rows),
            'total' => $total,
            'page'  => $page,
            'pages' => (int)ceil($total / $limit),
        ];
    }

    public function actionUpdateAppointment(int $id)
    {
        Yii::$app->response->format = 'json';
        $s = $this->getMySpecialist();

        $appointment = \common\models\Appointment::findOne(['id' => $id, 'specialist_id' => $s->id]);
        if (!$appointment) throw new NotFoundHttpException('Запис не знайдено');

        $data   = Yii::$app->request->post();
        $status = $data['status'] ?? null;

        $allowed = ['confirmed', 'completed', 'noshow', 'cancelled'];
        if ($status && in_array($status, $allowed)) {
            $appointment->status = $status;
            $appointment->save(false);
        }

        return ['success' => true, 'status' => $appointment->status];
    }

    // ── Schedule / Slots ─────────────────────────────────────────────

    public function actionMySlots()
    {
        Yii::$app->response->format = 'json';
        $s = $this->getMySpecialist();

        $slots = SpecialistSchedule::find()
            ->where(['specialist_id' => $s->id])
            ->orderBy(['day_of_week' => SORT_ASC, 'time_slot' => SORT_ASC])
            ->all();

        $byDay = [];
        foreach ($slots as $slot) {
            $byDay[(string)$slot->day_of_week][] = $slot->time_slot;
        }
        return $byDay;
    }

    public function actionSaveMySlots()
    {
        Yii::$app->response->format = 'json';
        $s    = $this->getMySpecialist();
        $data = Yii::$app->request->post();
        $slots = $data['slots'] ?? [];

        $transaction = Yii::$app->db->beginTransaction();
        try {
            SpecialistSchedule::deleteAll(['specialist_id' => $s->id]);
            foreach ($slots as $dow => $times) {
                foreach ((array)$times as $time) {
                    $row = new SpecialistSchedule();
                    $row->specialist_id = $s->id;
                    $row->day_of_week   = (int)$dow;
                    $row->time_slot     = $time;
                    $row->save(false);
                }
            }
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Не вдалося зберегти розклад'];
        }

        return ['success' => true];
    }

    public function actionMyWeekSchedule()
    {
        Yii::$app->response->format = 'json';
        $s = $this->getMySpecialist();

        $fromStr = Yii::$app->request->get('from', '');
        if ($fromStr && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)) {
            $monday = new \DateTime($fromStr);
        } else {
            $monday = new \DateTime();
            $dow    = (int)$monday->format('N');
            $monday->modify('-' . ($dow - 1) . ' days');
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d      = clone $monday;
            $d->modify("+{$i} days");
            $dateStr = $d->format('Y-m-d');
            $dowNum  = (int)$d->format('N');
            $dowKey  = $dowNum === 7 ? 0 : $dowNum;
            $days[]  = ['date' => $dateStr, 'dow' => $dowKey];
        }

        $dates        = array_column($days, 'date');
        $placeholders = implode(',', array_map(fn($d) => "'$d'", $dates));

        $allRows = Yii::$app->db->createCommand("
            SELECT 'template' AS row_type, day_of_week::text AS a, time_slot AS b, NULL::text AS c
            FROM specialist_schedule WHERE specialist_id = :id
            UNION ALL
            SELECT 'blocked', NULL, NULL, block_date::text
            FROM specialist_day_block
            WHERE specialist_id = :id AND block_date IN ($placeholders)
            UNION ALL
            SELECT 'booked', NULL, appointment_time, appointment_date::text
            FROM appointment
            WHERE specialist_id = :id AND appointment_date IN ($placeholders)
              AND status NOT IN ('cancelled')
        ", [':id' => $s->id])->queryAll();

        $template  = [];
        $blocked   = [];
        $bookedSet = [];
        foreach ($allRows as $r) {
            if ($r['row_type'] === 'template') {
                $template[(int)$r['a']][] = $r['b'];
            } elseif ($r['row_type'] === 'blocked') {
                $blocked[] = $r['c'];
            } else {
                $bookedSet[$r['c'] . '_' . $r['b']] = true;
            }
        }

        $result = [];
        foreach ($days as $day) {
            $date      = $day['date'];
            $dow       = $day['dow'];
            $isBlocked = in_array($date, $blocked);
            $times     = $template[$dow] ?? [];
            $slots     = [];
            foreach ($times as $t) {
                $key     = $date . '_' . $t;
                $slots[] = [
                    'time'   => $t,
                    'status' => $isBlocked ? 'blocked' : (isset($bookedSet[$key]) ? 'booked' : 'available'),
                ];
            }
            $result[] = ['date' => $date, 'dow' => $dow, 'blocked' => $isBlocked, 'slots' => $slots];
        }

        return ['week' => $result, 'template' => $template];
    }

    public function actionBlockMyDate()
    {
        Yii::$app->response->format = 'json';
        $s    = $this->getMySpecialist();
        $date = Yii::$app->request->post('date', '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Невірний формат дати'];
        }

        Yii::$app->db->createCommand()->upsert('specialist_day_block', [
            'specialist_id' => $s->id,
            'block_date'    => $date,
        ], false)->execute();

        return ['success' => true, 'date' => $date];
    }

    public function actionUnblockMyDate()
    {
        Yii::$app->response->format = 'json';
        $s    = $this->getMySpecialist();
        $date = Yii::$app->request->post('date', '');

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Невірний формат дати'];
        }

        Yii::$app->db->createCommand()->delete('specialist_day_block', [
            'specialist_id' => $s->id,
            'block_date'    => $date,
        ])->execute();

        return ['success' => true, 'date' => $date];
    }
}
