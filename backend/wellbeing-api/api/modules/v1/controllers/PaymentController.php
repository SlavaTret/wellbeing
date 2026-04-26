<?php

namespace api\modules\v1\controllers;

use common\models\Payment;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class PaymentController extends Controller
{
    public $modelClass = 'common\models\Payment';

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
        
        $query = Payment::find()->where(['user_id' => $user->id]);
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
        $payment = $this->findModel($id);
        $this->checkUserAccess($payment->user_id);
        return $payment;
    }

    public function actionCreate()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;
        $payment = new Payment();
        $payment->user_id = $user->id;

        $data = Yii::$app->request->post();
        if (!$payment->load($data, '') || !$payment->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $payment->getErrors()];
        }

        if ($payment->save()) {
            Yii::$app->response->statusCode = 201;
            return $payment;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create payment'];
    }

    public function actionProcess($id)
    {
        Yii::$app->response->format = 'json';
        $payment = $this->findModel($id);
        $this->checkUserAccess($payment->user_id);

        $data = Yii::$app->request->post();
        
        // Simulate payment processing
        $payment->payment_method = $data['payment_method'] ?? $payment->payment_method;
        $payment->transaction_id = $data['transaction_id'] ?? 'TXN-' . time();
        $payment->status = Payment::STATUS_COMPLETED;

        if ($payment->save(false)) {
            return [
                'success' => true,
                'message' => 'Оплата оброблена',
                'payment' => $payment,
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to process payment'];
    }

    private function findModel($id)
    {
        $model = Payment::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Payment not found');
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
