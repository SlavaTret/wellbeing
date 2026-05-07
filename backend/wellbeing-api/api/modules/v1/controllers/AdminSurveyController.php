<?php

namespace api\modules\v1\controllers;

use Yii;
use yii\rest\Controller;
use yii\web\NotFoundHttpException;

class AdminSurveyController extends Controller
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

    private function requireAdmin(): void
    {
        $user = Yii::$app->user->identity;
        if (!$user || !$user->is_admin) {
            throw new \yii\web\ForbiddenHttpException('Admin access required');
        }
    }

    public function actionIndex()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $rows = Yii::$app->db->createCommand("
            SELECT
                s.id, s.title, s.description, s.is_active, s.created_at,
                COUNT(DISTINCT sq.id) AS question_count,
                COUNT(DISTINCT sr.id) AS response_count
            FROM survey s
            LEFT JOIN survey_question sq ON sq.survey_id = s.id
            LEFT JOIN survey_response sr ON sr.survey_id = s.id
            GROUP BY s.id
            ORDER BY s.created_at DESC
        ")->queryAll();

        return array_map(fn($r) => [
            'id'             => (int)$r['id'],
            'title'          => $r['title'],
            'description'    => $r['description'],
            'is_active'      => (bool)$r['is_active'],
            'created_at'     => $r['created_at'],
            'question_count' => (int)$r['question_count'],
            'response_count' => (int)$r['response_count'],
        ], $rows);
    }

    public function actionCreate()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $data = Yii::$app->request->post();
        $title = trim($data['title'] ?? '');

        if ($title === '') {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'title is required'];
        }

        Yii::$app->db->createCommand()->insert('survey', [
            'title'       => $title,
            'description' => $data['description'] ?? null,
            'is_active'   => false,
        ])->execute();

        $id = (int)Yii::$app->db->getLastInsertID();
        return $this->surveyById($id);
    }

    public function actionUpdate($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $survey = $this->findSurvey((int)$id);
        $data   = Yii::$app->request->post();
        $fields = [];

        if (isset($data['title']))       $fields['title']       = $data['title'];
        if (array_key_exists('description', $data)) $fields['description'] = $data['description'];

        if ($fields) {
            Yii::$app->db->createCommand()->update('survey', $fields, ['id' => (int)$id])->execute();
        }

        return $this->surveyById((int)$id);
    }

    public function actionDelete($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);

        $responses = (int)Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM survey_response WHERE survey_id = :id',
            [':id' => (int)$id]
        )->queryScalar();

        if ($responses > 0) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Cannot delete survey with existing responses'];
        }

        Yii::$app->db->createCommand()->delete('survey_question', ['survey_id' => (int)$id])->execute();
        Yii::$app->db->createCommand()->delete('survey', ['id' => (int)$id])->execute();

        return ['success' => true];
    }

    public function actionActivate($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);

        $transaction = Yii::$app->db->beginTransaction();
        try {
            Yii::$app->db->createCommand()->update('survey', ['is_active' => false], '1=1')->execute();
            Yii::$app->db->createCommand()->update('survey', ['is_active' => true], ['id' => (int)$id])->execute();
            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Failed to activate survey'];
        }

        return $this->surveyById((int)$id);
    }

    public function actionQuestions($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);

        $rows = Yii::$app->db->createCommand(
            'SELECT id, question, sort_order, options FROM survey_question WHERE survey_id = :sid ORDER BY sort_order ASC',
            [':sid' => (int)$id]
        )->queryAll();

        return array_map(fn($q) => [
            'id'         => (int)$q['id'],
            'question'   => $q['question'],
            'sort_order' => (int)$q['sort_order'],
            'options'    => $this->decodeOptions($q['options']),
        ], $rows);
    }

    public function actionCreateQuestion($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);

        $data     = Yii::$app->request->post();
        $question = trim($data['question'] ?? '');
        $options  = $data['options'] ?? [];

        if ($question === '') {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'question is required'];
        }

        Yii::$app->db->createCommand()->insert('survey_question', [
            'survey_id'  => (int)$id,
            'question'   => $question,
            'sort_order' => (int)($data['sort_order'] ?? 0),
            'options'    => json_encode($options),
        ])->execute();

        $qid = (int)Yii::$app->db->getLastInsertID();
        $q   = Yii::$app->db->createCommand(
            'SELECT id, question, sort_order, options FROM survey_question WHERE id = :id',
            [':id' => $qid]
        )->queryOne();

        return [
            'id'         => (int)$q['id'],
            'question'   => $q['question'],
            'sort_order' => (int)$q['sort_order'],
            'options'    => $this->decodeOptions($q['options']),
        ];
    }

    public function actionUpdateQuestion($id, $qid)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);
        $q = $this->findQuestion((int)$qid, (int)$id);

        $data   = Yii::$app->request->post();
        $fields = [];

        if (isset($data['question']))   $fields['question']   = $data['question'];
        if (isset($data['sort_order'])) $fields['sort_order'] = (int)$data['sort_order'];
        if (isset($data['options']))    $fields['options']    = json_encode($data['options']);

        if ($fields) {
            Yii::$app->db->createCommand()->update('survey_question', $fields, ['id' => (int)$qid])->execute();
        }

        $q = Yii::$app->db->createCommand(
            'SELECT id, question, sort_order, options FROM survey_question WHERE id = :id',
            [':id' => (int)$qid]
        )->queryOne();

        return [
            'id'         => (int)$q['id'],
            'question'   => $q['question'],
            'sort_order' => (int)$q['sort_order'],
            'options'    => $this->decodeOptions($q['options']),
        ];
    }

    public function actionDeleteQuestion($id, $qid)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);
        $this->findQuestion((int)$qid, (int)$id);

        Yii::$app->db->createCommand()->delete('survey_question', ['id' => (int)$qid])->execute();

        return ['success' => true];
    }

    public function actionResults($id)
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $this->requireAdmin();

        $this->findSurvey((int)$id);

        $questions = Yii::$app->db->createCommand(
            'SELECT id, question, options FROM survey_question WHERE survey_id = :sid ORDER BY sort_order ASC',
            [':sid' => (int)$id]
        )->queryAll();

        $responses = Yii::$app->db->createCommand(
            'SELECT answers FROM survey_response WHERE survey_id = :sid',
            [':sid' => (int)$id]
        )->queryAll();

        $result = [];
        foreach ($questions as $q) {
            $options = $this->decodeOptions($q['options']);
            $counts  = array_fill(0, count($options), 0);
            $total   = 0;

            foreach ($responses as $r) {
                $raw = $r['answers'];
                $answers = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?? []);
                $qidStr  = (string)$q['id'];
                if (isset($answers[$qidStr])) {
                    $idx = (int)$answers[$qidStr];
                    if (isset($counts[$idx])) {
                        $counts[$idx]++;
                        $total++;
                    }
                }
            }

            $result[] = [
                'question_id' => (int)$q['id'],
                'question'    => $q['question'],
                'options'     => $options,
                'counts'      => $counts,
                'total'       => $total,
            ];
        }

        return $result;
    }

    private function decodeOptions(mixed $raw): array
    {
        if (is_array($raw)) return $raw;
        $once = json_decode((string)$raw, true);
        if (is_array($once)) return $once;
        // JSONB stored as double-encoded string — decode inner value
        if (is_string($once)) {
            $twice = json_decode($once, true);
            if (is_array($twice)) return $twice;
        }
        return [];
    }

    private function findSurvey(int $id): array
    {
        $survey = Yii::$app->db->createCommand(
            'SELECT id, title, description, is_active, created_at FROM survey WHERE id = :id',
            [':id' => $id]
        )->queryOne();

        if (!$survey) {
            throw new NotFoundHttpException('Survey not found');
        }

        return $survey;
    }

    private function findQuestion(int $qid, int $surveyId): array
    {
        $q = Yii::$app->db->createCommand(
            'SELECT id, question, sort_order, options FROM survey_question WHERE id = :id AND survey_id = :sid',
            [':id' => $qid, ':sid' => $surveyId]
        )->queryOne();

        if (!$q) {
            throw new NotFoundHttpException('Question not found');
        }

        return $q;
    }

    private function surveyById(int $id): array
    {
        $r = Yii::$app->db->createCommand("
            SELECT
                s.id, s.title, s.description, s.is_active, s.created_at,
                COUNT(DISTINCT sq.id) AS question_count,
                COUNT(DISTINCT sr.id) AS response_count
            FROM survey s
            LEFT JOIN survey_question sq ON sq.survey_id = s.id
            LEFT JOIN survey_response sr ON sr.survey_id = s.id
            WHERE s.id = :id
            GROUP BY s.id
        ", [':id' => $id])->queryOne();

        return [
            'id'             => (int)$r['id'],
            'title'          => $r['title'],
            'description'    => $r['description'],
            'is_active'      => (bool)$r['is_active'],
            'created_at'     => $r['created_at'],
            'question_count' => (int)$r['question_count'],
            'response_count' => (int)$r['response_count'],
        ];
    }
}
