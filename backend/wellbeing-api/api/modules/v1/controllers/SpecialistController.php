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
            'class'   => \yii\filters\auth\HttpBearerAuth::class,
            'optional' => ['index'],
        ];
        return $behaviors;
    }

    /**
     * GET v1/specialist
     */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        // 1. Load AR models with schedules (2 queries via eager load)
        $specialists = Specialist::find()
            ->where(['is_active' => true])
            ->with('schedules')
            ->orderBy(['name' => SORT_ASC])
            ->all();

        if (!$specialists) {
            return [];
        }

        // 2. Fetch real review stats in ONE query
        $ids   = array_map(fn($s) => $s->id, $specialists);
        $stats = Yii::$app->db->createCommand(
            'SELECT specialist_id,
                    COUNT(*)                            AS reviews_count,
                    ROUND(AVG(rating)::numeric, 1)      AS avg_rating
             FROM {{%specialist_review}}
             WHERE specialist_id IN (' . implode(',', $ids) . ')
             GROUP BY specialist_id'
        )->queryAll();

        // Index by specialist_id for O(1) lookup
        $statMap = [];
        foreach ($stats as $row) {
            $statMap[(int)$row['specialist_id']] = $row;
        }

        $result = [];
        foreach ($specialists as $s) {
            $reviewsCount = (int)($statMap[$s->id]['reviews_count'] ?? 0);
            $avgRating    = $reviewsCount > 0
                ? (float)$statMap[$s->id]['avg_rating']
                : null;

            // Sync cached columns (non-blocking write)
            if ($s->reviews_count !== $reviewsCount || (float)$s->rating !== (float)($avgRating ?? $s->rating)) {
                Yii::$app->db->createCommand()->update(
                    '{{%specialist}}',
                    ['reviews_count' => $reviewsCount, 'rating' => $avgRating ?? $s->rating],
                    ['id' => $s->id]
                )->execute();
            }

            $result[] = [
                'id'               => $s->id,
                'name'             => $s->name,
                'type'             => $s->type,
                'bio'              => $s->bio,
                'experience_years' => $s->experience_years,
                'rating'           => $avgRating,
                'reviews_count'    => $reviewsCount,
                'categories'       => $s->getCategoriesArray(),
                'avatar_initials'  => $s->avatar_initials,
                'price'            => (float)$s->price,
                'available_slots'  => $s->getAvailableSlots(14),
            ];
        }

        return $result;
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
