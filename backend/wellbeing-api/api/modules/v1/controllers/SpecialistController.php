<?php

namespace api\modules\v1\controllers;

use common\models\Specialist;
use common\models\SpecialistReview;
use Yii;
use yii\rest\Controller;

class SpecialistController extends Controller
{
    public $modelClass = 'common\models\Specialist';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // actionIndex is public; actionReview requires auth
        $behaviors['authentication'] = [
            'class'    => \yii\filters\auth\HttpBearerAuth::class,
            'optional' => ['index', 'categories'],
        ];
        return $behaviors;
    }

    /**
     * GET v1/specialist
     * 2 raw SQL queries total — no Active Record, no schema introspection overhead.
     * Response cached for 60s (slots are date-relative so cache key includes today's date).
     */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';
        $db        = Yii::$app->db;
        $kyivNow   = new \DateTime('now', new \DateTimeZone('Europe/Kyiv'));
        $todayKyiv = $kyivNow->format('Y-m-d');
        $nowMin    = (int)$kyivNow->format('H') * 60 + (int)$kyivNow->format('i') + 30;
        $cacheKey  = 'specialists_list_v4_' . $kyivNow->format('Y-m-d-H');
        $cached    = Yii::$app->cache->get($cacheKey);
        if ($cached !== false) {
            return $cached;
        }

        // Query 1: specialists + schedule slots + review stats in one JOIN
        $rows = $db->createCommand("
            SELECT
                s.id, s.name, s.type, s.bio, s.experience_years,
                s.categories, s.avatar_initials, s.avatar_url, s.price,
                COALESCE(sp.name, s.type) AS type_name,
                COALESCE(rv.avg_rating,    0) AS avg_rating,
                COALESCE(rv.reviews_count, 0) AS reviews_count,
                ss.day_of_week,
                ss.time_slot
            FROM specialist s
            LEFT JOIN specialization sp ON sp.key = s.type
            LEFT JOIN (
                SELECT specialist_id,
                       ROUND(AVG(rating)::numeric, 1) AS avg_rating,
                       COUNT(*)                        AS reviews_count
                FROM specialist_review
                GROUP BY specialist_id
            ) rv ON rv.specialist_id = s.id
            LEFT JOIN specialist_schedule ss ON ss.specialist_id = s.id
            WHERE s.is_active = TRUE
            ORDER BY s.name ASC, ss.day_of_week ASC, ss.time_slot ASC
        ")->queryAll();

        if (!$rows) {
            return [];
        }

        // Group schedule rows by specialist
        $specialists = [];
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            if (!isset($specialists[$id])) {
                $specialists[$id] = $r;
                $specialists[$id]['byDay'] = [];
            }
            if ($r['day_of_week'] !== null) {
                $specialists[$id]['byDay'][(int)$r['day_of_week']][] = $r['time_slot'];
            }
        }

        if (!$specialists) {
            return [];
        }

        $idList = implode(',', array_keys($specialists));
        $today  = \DateTime::createFromFormat('Y-m-d', $todayKyiv);
        $end       = (clone $today)->modify('+14 days');
        $from      = $todayKyiv;
        $to        = $end->format('Y-m-d');

        // Query 2: blocked dates + booked slots for all specialists via UNION
        $blockedMap = [];
        $bookedMap  = [];
        foreach ($db->createCommand("
            SELECT 'b' AS t, specialist_id, block_date::text AS d, NULL AS tm
            FROM specialist_day_block
            WHERE specialist_id IN ($idList)
              AND block_date >= :from AND block_date <= :to
            UNION ALL
            SELECT 'a', specialist_id, appointment_date::text, appointment_time
            FROM appointment
            WHERE specialist_id IN ($idList)
              AND appointment_date >= :from AND appointment_date <= :to
              AND status NOT IN ('cancelled')
        ", [':from' => $from, ':to' => $to])->queryAll() as $r) {
            $sid = (int)$r['specialist_id'];
            if ($r['t'] === 'b') {
                $blockedMap[$sid][$r['d']] = true;
            } else {
                $bookedMap[$sid][$r['d'] . '_' . $r['tm']] = true;
            }
        }

        $slotMin = fn(string $t): int => (int)explode(':', $t)[0] * 60 + (int)explode(':', $t)[1];

        $result = [];
        foreach ($specialists as $id => $s) {
            $blocked = $blockedMap[$id] ?? [];
            $booked  = $bookedMap[$id]  ?? [];
            $byDay   = $s['byDay'];

            $slots = [];
            $d = clone $today;
            while ($d <= $end) {
                $dow     = (int)$d->format('w');
                $dateStr = $d->format('Y-m-d');
                if (isset($byDay[$dow]) && !isset($blocked[$dateStr])) {
                    $free = array_values(array_filter(
                        $byDay[$dow],
                        fn($t) => !isset($booked[$dateStr . '_' . $t])
                            && ($dateStr !== $todayKyiv || $slotMin($t) > $nowMin)
                    ));
                    if ($free) {
                        $slots[] = ['date' => $dateStr, 'slots' => $free];
                    }
                }
                $d->modify('+1 day');
            }

            $cats = $s['categories']
                ? array_values(array_filter(array_map('trim', explode(',', $s['categories']))))
                : [];

            $reviewsCount = (int)$s['reviews_count'];
            $result[] = [
                'id'               => $id,
                'name'             => $s['name'],
                'type'             => $s['type'],
                'bio'              => $s['bio'],
                'experience_years' => (int)$s['experience_years'],
                'rating'           => $reviewsCount > 0 ? (float)$s['avg_rating'] : null,
                'reviews_count'    => $reviewsCount,
                'categories'       => $cats,
                'avatar_initials'  => $s['avatar_initials'],
                'avatar_url'       => $s['avatar_url'] ?? null,
                'type_name'        => $s['type_name'],
                'price'            => (float)$s['price'],
                'available_slots'  => $slots,
            ];
        }

        Yii::$app->cache->set($cacheKey, $result, 60);
        return $result;
    }

    /**
     * GET v1/categories — public list of active categories
     */
    public function actionCategories()
    {
        Yii::$app->response->format = 'json';

        return Yii::$app->db->createCommand(
            "SELECT id, name FROM \"category\" WHERE status = 'active' ORDER BY name ASC"
        )->queryAll();
    }

    /**
     * POST v1/specialist/<id>/review
     * Body: { rating: 1-5, comment: "...", appointment_id: 123 }
     */
    public function actionReview($id)
    {
        Yii::$app->response->format = 'json';

        $specialist = Specialist::findOne($id);
        if (!$specialist) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Спеціаліста не знайдено'];
        }

        $user = Yii::$app->user->identity;
        $data = Yii::$app->request->post();

        $rating        = (int)($data['rating'] ?? 0);
        $comment       = $data['comment'] ?? '';
        $appointmentId = $data['appointment_id'] ?? null;

        if ($rating < 1 || $rating > 5) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Оцінка має бути від 1 до 5'];
        }

        // Prevent duplicate review for same appointment
        if ($appointmentId) {
            $exists = SpecialistReview::find()
                ->where(['appointment_id' => $appointmentId])
                ->exists();
            if ($exists) {
                Yii::$app->response->statusCode = 409;
                return ['error' => 'Відгук для цього запису вже існує'];
            }
        }

        $review = new SpecialistReview();
        $review->specialist_id  = $specialist->id;
        $review->user_id        = $user->id;
        $review->appointment_id = $appointmentId ?: null;
        $review->rating         = $rating;
        $review->comment        = $comment;

        if (!$review->save()) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Не вдалось зберегти відгук'];
        }

        // Recalculate specialist avg rating and reviews_count
        $avg = SpecialistReview::find()
            ->where(['specialist_id' => $specialist->id])
            ->average('rating');

        $count = SpecialistReview::find()
            ->where(['specialist_id' => $specialist->id])
            ->count();

        $specialist->rating        = round((float)$avg, 1);
        $specialist->reviews_count = (int)$count;
        $specialist->save(false);

        return [
            'success'       => true,
            'message'       => 'Відгук збережено',
            'new_rating'    => $specialist->rating,
            'reviews_count' => $specialist->reviews_count,
        ];
    }
}
