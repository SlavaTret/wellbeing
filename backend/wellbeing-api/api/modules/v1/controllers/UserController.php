<?php

namespace api\modules\v1\controllers;

use common\models\User;
use Yii;
use yii\rest\Controller;
use yii\web\NotFoundException;
use yii\web\BadRequestHttpException;

class UserController extends Controller
{
    public $modelClass = 'common\models\User';

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class' => \yii\filters\auth\HttpBearerAuth::class,
            'except' => ['login', 'register'],
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'only' => ['get', 'update', 'profile'],
            'rules' => [
                [
                    'allow' => true,
                    'actions' => ['get', 'update', 'profile'],
                    'roles' => ['@'],
                ],
            ],
        ];
        return $behaviors;
    }

    /**
     * Register new user
     */
    public function actionRegister()
    {
        Yii::$app->response->format = 'json';

        $data = Yii::$app->request->post();
        $user = new User();

        if (!$user->load($data, '') || !$user->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $user->getErrors()];
        }

        $user->setPassword($data['password']);
        $user->generateAuthKey();
        $user->generateAccessToken();

        if ($user->save()) {
            return [
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                ],
                'access_token' => $user->access_token,
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to create user'];
    }

    /**
     * Login user (generate access token)
     */
    public function actionLogin()
    {
        Yii::$app->response->format = 'json';

        $email = Yii::$app->request->post('email');
        $password = Yii::$app->request->post('password');

        if (!$email || !$password) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Email and password are required'];
        }

        $user = User::findByEmail($email);
        if (!$user || !$user->validatePassword($password)) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Invalid email or password'];
        }

        $user->generateAccessToken();
        $user->save(false);

        return [
            'success' => true,
            'access_token' => $user->access_token,
            'user' => $this->getUserData($user),
        ];
    }

    /**
     * Get current user profile
     */
    public function actionProfile()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        if (!$user) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Unauthorized'];
        }

        return $this->getUserData($user);
    }

    /**
     * Update user profile
     */
    public function actionUpdateProfile()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        if (!$user) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Unauthorized'];
        }

        $data = Yii::$app->request->post();

        // Only allow these fields to be updated
        $allowedFields = ['first_name', 'last_name', 'patronymic', 'phone', 'company', 'avatar_url', 'accepted_terms'];
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $user->$field = $data[$field];
            }
        }

        if (!$user->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $user->getErrors()];
        }

        if ($user->save()) {
            return [
                'success' => true,
                'message' => 'Профіль оновлено успішно',
                'user' => $this->getUserData($user),
            ];
        }

        Yii::$app->response->statusCode = 400;
        return ['error' => 'Failed to update profile'];
    }

    /**
     * Get grouped user data
     */
    private function getUserData($user)
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'patronymic' => $user->patronymic,
            'phone' => $user->phone,
            'company' => $user->company,
            'avatar_url' => $user->avatar_url,
            'accepted_terms' => $user->accepted_terms,
            'created_at' => $user->created_at,
        ];
    }

    /**
     * Logout
     */
    public function actionLogout()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        if ($user) {
            $user->access_token = null;
            $user->save(false);
        }

        return ['success' => true, 'message' => 'Logged out successfully'];
    }
}
