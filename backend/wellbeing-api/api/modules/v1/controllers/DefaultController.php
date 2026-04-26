<?php

namespace api\modules\v1\controllers;

use Yii;
use yii\rest\Controller;

class DefaultController extends Controller
{
    public function actionOptions()
    {
        Yii::$app->response->statusCode = 204;
        return '';
    }

    public function actionError()
    {
        $exception = Yii::$app->errorHandler->exception;
        if ($exception !== null) {
            return ['error' => $exception->getMessage(), 'code' => $exception->getCode()];
        }
        return ['error' => 'Unknown error'];
    }
}
