<?php

namespace api\modules\v1\controllers;

use common\models\Appointment;
use Yii;
use yii\rest\Controller;
use yii\data\ActiveDataProvider;

class AppointmentController extends Controller
{
    public $modelClass = 'common\models\Appointment';

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

    /**
     * List appointments for current user
     */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        $query = Appointment::find()->where(['user_id' => $user->id]);

        // Filter by status
        $status = Yii::$app->request->get('status');
        if ($status) {
            $query->andWhere(['status' => $status]);
        }

        $dataProvider = new ActiveDataProvider([
            'query' => $query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'defaultOrder' => ['appointment_date' => SORT_DESC],
            ],
        ]);

        return [
            'items' => $dataProvider->getModels(),
            'total' => $dataProvider->totalCount,
        ];
    }

    /**
     * Get single appointment
     */
    public function actionView($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        
        $this->checkUserAccess($appointment->user_id);

        return $appointment;
    }

    /**
     * Create appointment
     */
    public function actionCreate()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        $appointment = new Appointment();
        $appointment->user_id = $user->id;

        $data = Yii::$app->request->post();
        if (!$appointment->load($data, '') || !$appointment->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $appointment->getErrors()];
        }

        if ($appointment->save()) {
            Yii::$app->response->statusCode = 201;
            return $appointment;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create appointment'];
    }

    /**
     * Update appointment
     */
    public function actionUpdate($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        $data = Yii::$app->request->post();
        if (!$appointment->load($data, '') || !$appointment->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $appointment->getErrors()];
        }

        if ($appointment->save()) {
            return $appointment;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to update appointment'];
    }

    /**
     * Delete appointment
     */
    public function actionDelete($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        if ($appointment->delete()) {
            Yii::$app->response->statusCode = 204;
            return null;
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to delete appointment'];
    }

    /**
     * Cancel appointment
     */
    public function actionCancel($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        $appointment->status = Appointment::STATUS_CANCELLED;
        if ($appointment->save(false)) {
            return [
                'success' => true,
                'message' => 'Запис скасовано',
                'appointment' => $appointment,
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to cancel appointment'];
    }

    /**
     * Leave review
     */
    public function actionReview($id)
    {
        Yii::$app->response->format = 'json';

        $appointment = $this->findModel($id);
        $this->checkUserAccess($appointment->user_id);

        $data = Yii::$app->request->post();
        $appointment->notes = $data['notes'] ?? $appointment->notes;
        $appointment->status = Appointment::STATUS_COMPLETED;

        if ($appointment->save(false)) {
            return [
                'success' => true,
                'message' => 'Відгук збережено',
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to save review'];
    }

    private function findModel($id)
    {
        $model = Appointment::findOne($id);
        if (!$model) {
            Yii::$app->response->statusCode = 404;
            throw new \yii\web\NotFoundHttpException('Appointment not found');
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
