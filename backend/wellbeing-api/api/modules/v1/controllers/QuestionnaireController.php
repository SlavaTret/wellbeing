<?php

namespace api\modules\v1\controllers;

use common\models\Questionnaire;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class QuestionnaireController extends Controller
{
    public $modelClass = 'common\models\Questionnaire';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [
                [
                    'allow' => true,
                    'roles' => ['@'],
                ],
            ],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;
        
        $query = Questionnaire::find()->where(['user_id' => $user->id]);
        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
        ]);

        return [
            'items' => $dataProvider->getModels(),
            'total' => $dataProvider->totalCount,
        ];
    }

    public function actionView($id)
    {
        Yii::$app->response->format = 'json';
        $q = $this->findModel($id);
        $this->checkUserAccess($q->user_id);
        return $q;
    }

    public function actionLatest()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;

        $q = Questionnaire::find()
            ->where(['user_id' => $user->id])
            ->orderBy(['created_at' => SORT_DESC])
            ->one();

        if (!$q) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'No questionnaire found'];
        }

        return $q;
    }

    public function actionCreate()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;
        $q = new Questionnaire();
        $q->user_id = $user->id;

        $data = Yii::$app->request->post();
        
        // Calculate PHQ-9 score from answers
        if (isset($data['phq9_answers']) && is_array($data['phq9_answers'])) {
            $score = 0;
            foreach ($data['phq9_answers'] as $answer) {
                $score += (int)$answer;
            }
            $data['phq9_score'] = $score;
        }

        if (!$q->load($data, '') || !$q->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $q->getErrors()];
        }

        if ($q->save()) {
            Yii::$app->response->statusCode = 201;
            return [
                'success' => true,
                'questionnaire' => $q,
                'interpretation' => $q->getMoodInterpretation(),
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create questionnaire'];
    }

    public function actionUpdate($id)
    {
        Yii::$app->response->format = 'json';
        $q = $this->findModel($id);
        $this->checkUserAccess($q->user_id);

        $data = Yii::$app->request->post();
        
        // Recalculate PHQ-9 score
        if (isset($data['phq9_answers']) && is_array($data['phq9_answers'])) {
            $score = 0;
            foreach ($data['phq9_answers'] as $answer) {
                $score += (int)$answer;
            }
            $data['phq9_score'] = $score;
        }

        if (!$q->load($data, '') || !$q->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $q->getErrors()];
        }

        if ($q->save()) {
            return [
                'success' => true,
                'questionnaire' => $q,
                'interpretation' => $q->getMoodInterpretation(),
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to update questionnaire'];
    }

    private function findModel($id)
    {
        $model = Questionnaire::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Questionnaire not found');
        }
        return $model;
    }

    private function checkUserAccess($userId)
    {
        $user = Yii::$app->user->identity;
        if ($user->id !== $userId) {
            Yii::$app->response->statusCode = 403;
            throw new \yii\web\ForbiddenHttpException('Access denied');
        }
    }
}
