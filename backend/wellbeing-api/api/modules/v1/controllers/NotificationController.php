<?php

namespace api\modules\v1\controllers;

use common\models\Notification;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class NotificationController extends Controller
{
    public $modelClass = 'common\models\Notification';

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
        
        $query = Notification::find()->where(['user_id' => $user->id]);
        
        // Filter by read/unread
        $isRead = Yii::$app->request->get('is_read');
        if ($isRead !== null) {
            $query->andWhere(['is_read' => $isRead]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => ['pageSize' => 20],
            'sort' => ['defaultOrder' => ['created_at' => SORT_DESC]],
        ]);

        return [
            'items' => $dataProvider->getModels(),
            'total' => $dataProvider->totalCount,
            'unread_count' => Notification::find()
                ->where(['user_id' => $user->id, 'is_read' => false])
                ->count(),
        ];
    }

    public function actionView($id)
    {
        Yii::$app->response->format = 'json';
        $notif = $this->findModel($id);
        $this->checkUserAccess($notif->user_id);
        
        // Mark as read
        if (!$notif->is_read) {
            $notif->is_read = true;
            $notif->save(false);
        }

        return $notif;
    }

    public function actionMarkAsRead($id)
    {
        Yii::$app->response->format = 'json';
        $notif = $this->findModel($id);
        $this->checkUserAccess($notif->user_id);

        $notif->is_read = true;
        if ($notif->save(false)) {
            return [
                'success' => true,
                'message' => 'Сповіщення позначено як прочитане',
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to mark as read'];
    }

    public function actionMarkAllAsRead()
    {
        Yii::$app->response->format = 'json';
        $user = Yii::$app->user->identity;

        Notification::updateAll(['is_read' => true], ['user_id' => $user->id]);

        return [
            'success' => true,
            'message' => 'Всі сповіщення позначено як прочитані',
        ];
    }

    public function actionDelete($id)
    {
        Yii::$app->response->format = 'json';
        $notif = $this->findModel($id);
        $this->checkUserAccess($notif->user_id);

        if ($notif->delete()) {
            Yii::$app->response->statusCode = 204;
            return null;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to delete notification'];
    }

    private function findModel($id)
    {
        $model = Notification::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Notification not found');
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
