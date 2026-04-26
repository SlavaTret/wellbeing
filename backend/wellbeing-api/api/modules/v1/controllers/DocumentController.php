<?php

namespace api\modules\v1\controllers;

use common\models\Document;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class DocumentController extends Controller
{
    public $modelClass = 'common\models\Document';

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
        
        $query = Document::find()->where(['user_id' => $user->id]);
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
        $doc = $this->findModel($id);
        $this->checkUserAccess($doc->user_id);
        return $doc;
    }

    public function actionCreate()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;
        $doc = new Document();
        $doc->user_id = $user->id;

        $data = Yii::$app->request->post();
        if (!$doc->load($data, '') || !$doc->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $doc->getErrors()];
        }

        if ($doc->save()) {
            Yii::$app->response->statusCode = 201;
            return $doc;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create document'];
    }

    public function actionDelete($id)
    {
        Yii::$app->response->format = 'json';
        $doc = $this->findModel($id);
        $this->checkUserAccess($doc->user_id);

        if ($doc->delete()) {
            Yii::$app->response->statusCode = 204;
            return null;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to delete document'];
    }

    private function findModel($id)
    {
        $model = Document::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Document not found');
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
