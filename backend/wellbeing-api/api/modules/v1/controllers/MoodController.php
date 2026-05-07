<?php

namespace api\modules\v1\controllers;

use Yii;
use yii\rest\Controller;

class MoodController extends Controller
{
    // POST  v1/mood          — upsert today's mood
    // GET   v1/mood/today    — today's entry (null if none)
    // GET   v1/mood/history  — last N days

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

    private function userId(): int
    {
        return (int) Yii::$app->user->id;
    }

    // POST /v1/mood
    public function actionCreate()
    {
        Yii::$app->response->format = 'json';

        $body = Yii::$app->request->post();
        $mood = (int)($body['mood'] ?? 0);
        $note = $body['note'] ?? null;

        if ($mood < 1 || $mood > 5) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'mood must be between 1 and 5'];
        }

        $userId = $this->userId();
        $today  = date('Y-m-d');
        $db     = Yii::$app->db;

        // Upsert — one entry per user per day
        $existing = $db->createCommand(
            'SELECT id FROM mood_log WHERE user_id = :u AND logged_at = :d',
            [':u' => $userId, ':d' => $today]
        )->queryScalar();

        if ($existing) {
            $db->createCommand(
                'UPDATE mood_log SET mood = :m, note = :n, created_at = NOW()
                 WHERE id = :id',
                [':m' => $mood, ':n' => $note, ':id' => $existing]
            )->execute();
        } else {
            $db->createCommand(
                'INSERT INTO mood_log (user_id, mood, note, logged_at)
                 VALUES (:u, :m, :n, :d)',
                [':u' => $userId, ':m' => $mood, ':n' => $note, ':d' => $today]
            )->execute();
        }

        return ['success' => true, 'mood' => $mood, 'date' => $today];
    }

    // GET /v1/mood/today
    public function actionToday()
    {
        Yii::$app->response->format = 'json';

        $row = Yii::$app->db->createCommand(
            'SELECT mood, note, logged_at FROM mood_log
             WHERE user_id = :u AND logged_at = :d',
            [':u' => $this->userId(), ':d' => date('Y-m-d')]
        )->queryOne();

        return $row ?: null;
    }

    // GET /v1/mood/history?days=30
    public function actionHistory()
    {
        Yii::$app->response->format = 'json';

        $days = min((int)(Yii::$app->request->get('days', 30)), 90);

        $rows = Yii::$app->db->createCommand(
            'SELECT mood, note, logged_at
             FROM mood_log
             WHERE user_id = :u
               AND logged_at >= CURRENT_DATE - INTERVAL \'' . $days . ' days\'
             ORDER BY logged_at ASC',
            [':u' => $this->userId()]
        )->queryAll();

        return $rows;
    }
}
