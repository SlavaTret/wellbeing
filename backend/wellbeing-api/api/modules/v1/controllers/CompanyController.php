<?php

namespace api\modules\v1\controllers;

use common\models\Company;
use Yii;
use yii\rest\Controller;

class CompanyController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        // Public endpoint — needed during registration before user has token
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
            'except' => ['index'],
        ];
        return $behaviors;
    }

    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        $companies = Company::find()
            ->where(['is_active' => true])
            ->orderBy(['name' => SORT_ASC])
            ->all();

        return array_map(fn($c) => $c->toBrandingArray(), $companies);
    }
}
