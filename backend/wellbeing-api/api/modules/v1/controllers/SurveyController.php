<?php

namespace api\modules\v1\controllers;

use Yii;
use yii\rest\Controller;

class SurveyController extends Controller
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

    public function actionActive()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $survey = Yii::$app->db->createCommand(
            'SELECT id, title, description FROM survey WHERE is_active = TRUE LIMIT 1'
        )->queryOne();

        if (!$survey) {
            return null;
        }

        $questions = Yii::$app->db->createCommand(
            'SELECT id, question, sort_order, options FROM survey_question WHERE survey_id = :sid ORDER BY sort_order ASC',
            [':sid' => $survey['id']]
        )->queryAll();

        return [
            'id'          => (int)$survey['id'],
            'title'       => $survey['title'],
            'description' => $survey['description'],
            'questions'   => array_map(fn($q) => [
                'id'         => (int)$q['id'],
                'question'   => $q['question'],
                'sort_order' => (int)$q['sort_order'],
                'options'    => $this->decodeOptions($q['options']),
            ], $questions),
        ];
    }

    private function decodeOptions(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        $once = json_decode((string)$raw, true);
        if (is_array($once)) return $once;
        if (is_string($once)) {
            $twice = json_decode($once, true);
            if (is_array($twice)) return $twice;
        }
        return [];
    }

    public function actionMyStatus()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $userId = Yii::$app->user->id;

        $survey = Yii::$app->db->createCommand(
            'SELECT id FROM survey WHERE is_active = TRUE LIMIT 1'
        )->queryOne();

        if (!$survey) {
            return ['completed' => false, 'survey_id' => null];
        }

        $surveyId = (int)$survey['id'];

        $exists = (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM survey_response WHERE user_id = :uid AND survey_id = :sid',
            [':uid' => $userId, ':sid' => $surveyId]
        )->queryScalar();

        return ['completed' => $exists > 0, 'survey_id' => $surveyId];
    }

    public function actionRespond()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        $userId = Yii::$app->user->id;
        $data   = Yii::$app->request->post();

        $surveyId = isset($data['survey_id']) ? (int)$data['survey_id'] : 0;
        $answers  = $data['answers'] ?? null;

        if (!$surveyId || $answers === null) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'survey_id and answers are required'];
        }

        $survey = Yii::$app->db->createCommand(
            'SELECT id, is_active FROM survey WHERE id = :id',
            [':id' => $surveyId]
        )->queryOne();

        if (!$survey) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Survey not found'];
        }

        if (!$survey['is_active']) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Survey is not active'];
        }

        $existing = (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM survey_response WHERE user_id = :uid AND survey_id = :sid',
            [':uid' => $userId, ':sid' => $surveyId]
        )->queryScalar();

        if ($existing === 0) {
            Yii::$app->db->createCommand()->insert('survey_response', [
                'user_id'   => $userId,
                'survey_id' => $surveyId,
                'answers'   => json_encode($answers),
            ])->execute();
        }

        return ['success' => true];
    }
}
