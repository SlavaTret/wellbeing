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
            'only' => ['get', 'update', 'profile', 'upload-avatar'],
            'rules' => [
                [
                    'allow' => true,
                    'actions' => ['get', 'update', 'profile', 'upload-avatar'],
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

        // Username is required by the schema; derive from email if not given.
        if (empty($data['username']) && !empty($data['email'])) {
            $data['username'] = $data['email'];
        }

        if (!$user->load($data, '') || !$user->validate()) {
            Yii::$app->response->statusCode = 422;
            return ['errors' => $user->getErrors()];
        }

        $user->setPassword($data['password']);
        $user->generateAuthKey();
        $user->generateAccessToken();

        // Mirror company name into legacy `company` string column for backwards compatibility.
        if (!empty($user->company_id)) {
            $branding = \common\models\Company::findOne($user->company_id);
            if ($branding) {
                $user->company = $branding->name;
            }
        }

        if ($user->save()) {
            return [
                'success' => true,
                'user' => $this->getUserData($user),
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
        $allowedFields = ['first_name', 'last_name', 'patronymic', 'phone', 'company', 'company_id', 'avatar_url', 'accepted_terms'];
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
     * Get grouped user data including company branding info.
     */
    private function getUserData($user)
    {
        $branding = $user->companyBranding;
        return [
            'id'               => $user->id,
            'email'            => $user->email,
            'first_name'       => $user->first_name,
            'last_name'        => $user->last_name,
            'patronymic'       => $user->patronymic,
            'phone'            => $user->phone,
            'company'          => $user->company,
            'company_id'       => $user->company_id,
            'company_name'     => $branding ? $branding->name : ($user->company ?? ''),
            'company_branding' => $branding ? $branding->toBrandingArray() : null,
            'avatar_url'       => $user->avatar_url,
            'accepted_terms'   => $user->accepted_terms,
            'is_admin'         => (bool)$user->is_admin,
            'created_at'       => $user->created_at,
        ];
    }

    /**
     * Upload avatar
     */
    public function actionUploadAvatar()
    {
        Yii::$app->response->format = 'json';

        $user = Yii::$app->user->identity;
        if (!$user) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Unauthorized'];
        }

        $file = \yii\web\UploadedFile::getInstanceByName('avatar');
        if (!$file) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'No file uploaded'];
        }

        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array(strtolower($file->extension), $allowed)) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Only image files are allowed'];
        }

        if ($file->size > 5 * 1024 * 1024) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'File too large (max 5 MB)'];
        }

        $dir = Yii::getAlias('@webroot/uploads/avatars');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'user_' . $user->id . '_' . time() . '.' . $file->extension;
        $path = $dir . '/' . $filename;

        if (!$file->saveAs($path)) {
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Failed to save file'];
        }

        // Remove old avatar file if it was a local upload
        if ($user->avatar_url && str_contains($user->avatar_url, '/uploads/avatars/')) {
            $urlPath = parse_url($user->avatar_url, PHP_URL_PATH);
            $urlPath = preg_replace('#^/api#', '', $urlPath);
            $oldPath = Yii::getAlias('@webroot') . $urlPath;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }

        $user->avatar_url = '/api/uploads/avatars/' . $filename;
        $user->save(false);

        return [
            'success'    => true,
            'avatar_url' => $user->avatar_url,
            'user'       => $this->getUserData($user),
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
