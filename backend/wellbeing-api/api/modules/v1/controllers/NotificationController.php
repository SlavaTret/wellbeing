<?php

namespace api\modules\v1\controllers;

use common\models\Notification;
use common\models\NotificationSetting;
use Yii;
use yii\rest\Controller;

class NotificationController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = ['class' => \yii\filters\auth\HttpBearerAuth::class];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [['allow' => true, 'roles' => ['@']]],
        ];
        return $behaviors;
    }

    /** GET /v1/notification */
    public function actionIndex()
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $items = Notification::find()
            ->where(['user_id' => $userId])
            ->orderBy(['created_at' => SORT_DESC])
            ->all();

        return [
            'items'        => array_map(fn($n) => $n->toClientArray(), $items),
            'unread_count' => (int)Notification::find()->where(['user_id' => $userId, 'is_read' => false])->count(),
        ];
    }

    /** GET /v1/notification/unread-count */
    public function actionUnreadCount()
    {
        Yii::$app->response->format = 'json';
        return ['count' => (int)Notification::find()->where(['user_id' => Yii::$app->user->id, 'is_read' => false])->count()];
    }

    /** POST /v1/notification/<id>/read */
    public function actionRead($id)
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;

        $n = Notification::findOne(['id' => $id, 'user_id' => $userId]);
        if (!$n) {
            Yii::$app->response->statusCode = 404;
            return ['error' => 'Not found'];
        }
        $n->is_read = true;
        $n->save(false);

        return [
            'success'      => true,
            'unread_count' => (int)Notification::find()->where(['user_id' => $userId, 'is_read' => false])->count(),
        ];
    }

    /** POST /v1/notification/read-all */
    public function actionReadAll()
    {
        Yii::$app->response->format = 'json';
        $userId = Yii::$app->user->id;
        Notification::updateAll(['is_read' => true], ['user_id' => $userId, 'is_read' => false]);
        return ['success' => true, 'unread_count' => 0];
    }

    /** GET /v1/notification/settings */
    public function actionSettings()
    {
        Yii::$app->response->format = 'json';
        return $this->settingsArray(NotificationSetting::forUser(Yii::$app->user->id));
    }

    /** POST /v1/notification/save-settings */
    public function actionSaveSettings()
    {
        Yii::$app->response->format = 'json';
        $s    = NotificationSetting::forUser(Yii::$app->user->id);
        $data = Yii::$app->request->post();

        foreach (['email_enabled', 'calendar_enabled', 'sms_enabled', 'reminders_enabled'] as $f) {
            if (array_key_exists($f, $data)) {
                $s->$f = (bool)$data[$f];
            }
        }

        if ($s->save()) {
            return $this->settingsArray($s);
        }

        Yii::$app->response->statusCode = 422;
        return ['errors' => $s->getErrors()];
    }

    private function settingsArray(NotificationSetting $s): array
    {
        return [
            'email_enabled'     => (bool)$s->email_enabled,
            'calendar_enabled'  => (bool)$s->calendar_enabled,
            'sms_enabled'       => (bool)$s->sms_enabled,
            'reminders_enabled' => (bool)$s->reminders_enabled,
        ];
    }
}
