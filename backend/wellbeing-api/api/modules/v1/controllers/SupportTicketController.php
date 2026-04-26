<?php

namespace api\modules\v1\controllers;

use common\models\SupportTicket;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class SupportTicketController extends Controller
{
    public $modelClass = 'common\models\SupportTicket';

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
        
        $query = SupportTicket::find()->where(['user_id' => $user->id]);

        // Filter by status
        $status = Yii::$app->request->get('status');
        if ($status) {
            $query->andWhere(['status' => $status]);
        }

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
        $ticket = $this->findModel($id);
        $this->checkUserAccess($ticket->user_id);
        return $ticket;
    }

    public function actionCreate()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;
        $ticket = new SupportTicket();
        $ticket->user_id = $user->id;

        $data = Yii::$app->request->post();
        if (!$ticket->load($data, '') || !$ticket->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $ticket->getErrors()];
        }

        if ($ticket->save()) {
            Yii::$app->response->statusCode = 201;
            return $ticket;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create ticket'];
    }

    public function actionUpdate($id)
    {
        Yii::$app->response->format = 'json';
        $ticket = $this->findModel($id);
        $this->checkUserAccess($ticket->user_id);

        $data = Yii::$app->request->post();
        if (!$ticket->load($data, '') || !$ticket->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $ticket->getErrors()];
        }

        if ($ticket->save()) {
            return $ticket;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to update ticket'];
    }

    public function actionReply($id)
    {
        Yii::$app->response->format = 'json';
        $ticket = $this->findModel($id);
        // Note: This could be admin-only in production
        // $this->checkUserAccess($ticket->user_id);

        $data = Yii::$app->request->post();
        $ticket->response_message = $data['response_message'] ?? $ticket->response_message;
        $ticket->status = SupportTicket::STATUS_RESOLVED;

        if ($ticket->save(false)) {
            return [
                'success' => true,
                'message' => 'Відповідь відправлена',
                'ticket' => $ticket,
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to send reply'];
    }

    public function actionClose($id)
    {
        Yii::$app->response->format = 'json';
        $ticket = $this->findModel($id);
        $this->checkUserAccess($ticket->user_id);

        $ticket->status = SupportTicket::STATUS_CLOSED;
        if ($ticket->save(false)) {
            return [
                'success' => true,
                'message' => 'Тикет закрито',
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to close ticket'];
    }

    private function findModel($id)
    {
        $model = SupportTicket::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Support ticket not found');
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
