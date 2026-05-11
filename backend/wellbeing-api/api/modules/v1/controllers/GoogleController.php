<?php

namespace api\modules\v1\controllers;

use common\models\NotificationSetting;
use common\models\UserGoogleToken;
use common\services\GoogleCalendarService;
use Yii;
use yii\rest\Controller;

class GoogleController extends Controller
{
    public $modelClass = 'common\models\User';

    private function service(): GoogleCalendarService
    {
        return new GoogleCalendarService();
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authentication'] = [
            'class'    => \yii\filters\auth\HttpBearerAuth::class,
            'optional' => ['callback'],
        ];
        $behaviors['access'] = [
            'class' => \yii\filters\AccessControl::class,
            'rules' => [
                [
                    'allow'   => true,
                    'actions' => ['auth-url', 'disconnect', 'status', 'upcoming-events'],
                    'roles'   => ['@'],
                ],
                [
                    'allow'   => true,
                    'actions' => ['callback'],
                    'roles'   => ['?', '@'],
                ],
            ],
        ];
        return $behaviors;
    }

    /**
     * GET v1/google/auth-url
     * Returns the Google OAuth2 authorization URL.
     */
    public function actionAuthUrl()
    {
        Yii::$app->response->format = 'json';

        $svc = $this->service();
        if (!$svc->isConfigured()) {
            Yii::$app->response->statusCode = 503;
            return ['error' => 'Google OAuth не налаштовано. Зверніться до адміністратора.'];
        }

        $user = Yii::$app->user->identity;
        return ['url' => $svc->getAuthUrl($user->id)];
    }

    /**
     * GET v1/google/callback?code=...&state=...
     * Handles the OAuth redirect from Google — no Bearer auth.
     */
    public function actionCallback()
    {
        $code  = Yii::$app->request->get('code', '');
        $state = Yii::$app->request->get('state', '');
        $error = Yii::$app->request->get('error', '');

        $frontendBase = rtrim(Yii::$app->params['frontendUrl'] ?? 'http://localhost:4200', '/');

        if ($error || !$code) {
            return Yii::$app->response->redirect($frontendBase . '/notifications?google=error');
        }

        // Decode state → user_id
        $decoded = base64_decode($state);
        $userId  = (int)explode(':', $decoded)[0];
        if (!$userId) {
            return Yii::$app->response->redirect($frontendBase . '/notifications?google=error');
        }

        $svc  = $this->service();
        $data = $svc->exchangeCode($code);

        if (empty($data['access_token'])) {
            return Yii::$app->response->redirect($frontendBase . '/notifications?google=error');
        }

        $googleEmail = $svc->getUserEmail($data['access_token']);

        $token = UserGoogleToken::findOne(['user_id' => $userId]) ?? new UserGoogleToken();
        $token->user_id       = $userId;
        $token->access_token  = $data['access_token'];
        $token->refresh_token = $data['refresh_token'] ?? $token->refresh_token;
        $token->expires_at    = time() + (int)($data['expires_in'] ?? 3600);
        $token->google_email  = $googleEmail;
        $token->updated_at    = time();
        if (!$token->created_at) {
            $token->created_at = time();
        }
        $token->save(false);

        return Yii::$app->response->redirect($frontendBase . '/settings?tab=integrations&google=connected');
    }

    /**
     * GET v1/google/status
     */
    public function actionStatus()
    {
        Yii::$app->response->format = 'json';

        $user  = Yii::$app->user->identity;
        $token = UserGoogleToken::forUser($user->id);

        return [
            'connected'    => (bool)$token,
            'google_email' => $token?->google_email ?? null,
        ];
    }

    /**
     * POST v1/google/disconnect
     */
    public function actionDisconnect()
    {
        Yii::$app->response->format = 'json';

        $user  = Yii::$app->user->identity;
        $token = UserGoogleToken::forUser($user->id);

        if ($token) {
            try {
                $this->service()->revokeToken($token->access_token);
            } catch (\Throwable $e) {
                // Ignore revocation errors — token might already be invalid
            }
            $token->delete();
        }

        return ['success' => true];
    }

    /**
     * GET v1/google/upcoming-events
     * Real-time upcoming events from user's primary Google Calendar.
     */
    public function actionUpcomingEvents()
    {
        Yii::$app->response->format = 'json';

        $user  = Yii::$app->user->identity;
        $token = UserGoogleToken::forUser($user->id);

        if (!$token) {
            return ['connected' => false, 'events' => []];
        }

        try {
            $events = $this->service()->getUpcomingEvents($token);
        } catch (\Throwable $e) {
            // Token exists but events fetch failed (expired access token, network, etc.)
            // Still report connected=true so the dashboard doesn't show "connect" prompt.
            return ['connected' => true, 'events' => [], 'error' => 'Не вдалося завантажити події календаря'];
        }

        // Only show events created by Wellbeing (identified by description marker)
        $wellbeingEvents = array_filter($events, function ($e) {
            $desc = $e['description'] ?? '';
            return str_contains($desc, 'Wellbeing');
        });

        $result = array_values(array_map(fn($e) => [
            'id'        => $e['id'],
            'title'     => $e['summary'] ?? 'Консультація',
            'start'     => $e['start']['dateTime'] ?? $e['start']['date'] ?? null,
            'end'       => $e['end']['dateTime']   ?? $e['end']['date']   ?? null,
            'meet_link' => $e['hangoutLink'] ?? null,
            'html_link' => $e['htmlLink']    ?? null,
        ], $wellbeingEvents));

        return ['connected' => true, 'events' => $result];
    }
}
