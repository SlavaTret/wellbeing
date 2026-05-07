<?php

$params = array_merge(
    require __DIR__ . '/../../common/config/params.php',
    require __DIR__ . '/../../common/config/params-local.php',
    require __DIR__ . '/params.php',
    require __DIR__ . '/params-local.php'
);

return [
    'id' => 'app-api',
    'basePath' => dirname(__DIR__),
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'modules' => [
        'v1' => [
            'class' => 'api\modules\v1\Module',
        ],
    ],
    'components' => [
        'request' => [
            'parsers' => [
                'application/json' => 'yii\web\JsonParser',
            ],
            'enableCsrfValidation' => false,
        ],
        'response' => [
            'format' => yii\web\Response::FORMAT_JSON,
            'charset' => 'UTF-8',
        ],
        'user' => [
            'identityClass' => 'common\models\User',
            'enableAutoLogin' => false,
            'enableSession' => false,
            'loginUrl' => null,
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'errorHandler' => [
            'errorAction' => 'v1/default/error',
        ],
        'urlManager' => [
            'enablePrettyUrl' => true,
            'enableStrictParsing' => true,
            'showScriptName' => false,
            'rules' => [
                // Portal settings (public)
                'GET  v1/portal-settings'        => 'v1/portal-settings/index',

                // Companies (public)
                'GET  v1/company'               => 'v1/company/index',

                // Specialists
                'GET  v1/specialist'                         => 'v1/specialist/index',
                'POST v1/specialist/<id:\d+>/review'         => 'v1/specialist/review',

                // Categories (public — active only)
                'GET  v1/categories'                         => 'v1/specialist/categories',

                // Auth
                'POST v1/user/login'            => 'v1/user/login',
                'POST v1/user/register'         => 'v1/user/register',
                'POST v1/user/logout'           => 'v1/user/logout',
                'GET  v1/user/profile'          => 'v1/user/profile',
                'POST v1/user/update-profile'   => 'v1/user/update-profile',
                'POST v1/user/upload-avatar'    => 'v1/user/upload-avatar',

                // Dashboard
                'GET v1/dashboard/free-sessions' => 'v1/dashboard/free-sessions',
                'GET v1/dashboard'               => 'v1/dashboard/index',

                // Notifications
                'GET  v1/notification'                        => 'v1/notification/index',
                'GET  v1/notification/unread-count'           => 'v1/notification/unread-count',
                'GET  v1/notification/settings'               => 'v1/notification/settings',
                'POST v1/notification/save-settings'          => 'v1/notification/save-settings',
                'POST v1/notification/read-all'               => 'v1/notification/read-all',
                'POST v1/notification/<id:\d+>/read'          => 'v1/notification/read',

                // Appointments
                'GET  v1/appointment'           => 'v1/appointment/index',
                'POST v1/appointment'           => 'v1/appointment/create',
                'GET  v1/appointment/<id:\d+>'  => 'v1/appointment/view',
                'POST v1/appointment/<id:\d+>'  => 'v1/appointment/update',
                'POST v1/appointment/<id:\d+>/cancel' => 'v1/appointment/cancel',
                'POST v1/appointment/<id:\d+>/review' => 'v1/appointment/review',
                'DELETE v1/appointment/<id:\d+>' => 'v1/appointment/delete',

                // Documents
                'GET  v1/document'              => 'v1/document/index',
                'POST v1/document/upload'       => 'v1/document/upload',
                'DELETE v1/document/<id:\d+>'   => 'v1/document/delete',

                // Diagnostics (remove before production)
                'GET v1/debug/liqpay' => 'v1/payment/debug-liqpay',

                // Payments
                'GET  v1/payment'                                => 'v1/payment/index',
                'POST v1/payment/<id:\d+>/initiate'              => 'v1/payment/initiate',
                'POST v1/payment/<id:\d+>/sync'                  => 'v1/payment/sync',
                'POST v1/payment/sync-by-order'                  => 'v1/payment/sync-by-order',
                'POST v1/payment/<id:\d+>/process'               => 'v1/payment/process',
                // Payment gateway callbacks (public, no auth)
                'POST v1/payment/callback/<gateway:[a-z]+>'      => 'v1/payment-callback/handle',

                // Admin
                'GET    v1/admin/dashboard'              => 'v1/admin/dashboard',
                'GET    v1/admin/companies'              => 'v1/admin/companies',
                'POST   v1/admin/upload-logo'            => 'v1/admin/upload-logo',
                'POST   v1/admin/companies'              => 'v1/admin/create-company',
                'POST   v1/admin/companies/<id:\d+>'     => 'v1/admin/update-company',
                'DELETE v1/admin/companies/<id:\d+>'     => 'v1/admin/delete-company',

                'GET    v1/admin/users'                  => 'v1/admin/users',
                'POST   v1/admin/users'                  => 'v1/admin/create-user',
                'POST   v1/admin/users/<id:\d+>'         => 'v1/admin/update-user',
                'DELETE v1/admin/users/<id:\d+>'         => 'v1/admin/delete-user',

                'GET    v1/admin/categories'                      => 'v1/admin/admin-categories',
                'POST   v1/admin/categories'                     => 'v1/admin/create-category',
                'POST   v1/admin/categories/<id:\d+>'            => 'v1/admin/update-category',
                'DELETE v1/admin/categories/<id:\d+>'            => 'v1/admin/delete-category',

                'GET    v1/admin/appointments'                    => 'v1/admin/admin-appointments',
                'POST   v1/admin/appointments'                   => 'v1/admin/create-admin-appointment',
                'POST   v1/admin/appointments/<id:\d+>'          => 'v1/admin/update-admin-appointment',
                'DELETE v1/admin/appointments/<id:\d+>'          => 'v1/admin/delete-admin-appointment',

                'GET    v1/admin/payments'                       => 'v1/admin/admin-payments',
                'POST   v1/admin/payments/<id:\d+>'              => 'v1/admin/update-admin-payment',

                'GET    v1/admin/specialists'                    => 'v1/admin/admin-specialists',
                'POST   v1/admin/specialists'                    => 'v1/admin/create-specialist',
                'POST   v1/admin/specialists/<id:\d+>'           => 'v1/admin/update-specialist',
                'DELETE v1/admin/specialists/<id:\d+>'           => 'v1/admin/delete-specialist',
                'POST   v1/admin/specialists/<id:\d+>/upload-avatar' => 'v1/admin/upload-specialist-avatar',
                'GET    v1/admin/specialists/<id:\d+>/slots'          => 'v1/admin/specialist-slots',
                'POST   v1/admin/specialists/<id:\d+>/slots'          => 'v1/admin/save-specialist-slots',
                'GET    v1/admin/specialists/<id:\d+>/available-slots'  => 'v1/admin/admin-specialist-available-slots',
                'GET    v1/admin/specialists/<id:\d+>/week-schedule'   => 'v1/admin/specialist-week-schedule',
                'POST   v1/admin/specialists/<id:\d+>/block-date'      => 'v1/admin/block-specialist-date',
                'DELETE v1/admin/specialists/<id:\d+>/block-date'      => 'v1/admin/unblock-specialist-date',

                'GET    v1/admin/specializations'                => 'v1/admin/admin-specializations',
                'POST   v1/admin/specializations'                => 'v1/admin/create-specialization',
                'POST   v1/admin/specializations/<id:\d+>'       => 'v1/admin/update-specialization',
                'DELETE v1/admin/specializations/<id:\d+>'       => 'v1/admin/delete-specialization',

                // Admin Settings
                'GET    v1/admin/settings'                       => 'v1/admin/settings',
                'POST   v1/admin/settings'                       => 'v1/admin/save-settings',
                'POST   v1/admin/settings/upload-favicon'        => 'v1/admin/upload-favicon',
                'GET    v1/admin/payment-settings'               => 'v1/admin/payment-settings',
                'POST   v1/admin/payment-settings'               => 'v1/admin/save-payment-settings',
                'POST   v1/admin/payments/<id:\d+>/check-status' => 'v1/admin/check-payment-status',

                // Google Calendar (user)
                'GET    v1/google/auth-url'                      => 'v1/google/auth-url',
                'GET    v1/google/callback'                      => 'v1/google/callback',
                'POST   v1/google/disconnect'                    => 'v1/google/disconnect',
                'GET    v1/google/status'                        => 'v1/google/status',
                'GET    v1/google/upcoming-events'               => 'v1/google/upcoming-events',

                // Notifications
                'GET  v1/notification'          => 'v1/notification/index',
                'GET  v1/notification/<id:\d+>' => 'v1/notification/view',
                'POST v1/notification/<id:\d+>/mark-as-read' => 'v1/notification/mark-as-read',
                'POST v1/notification/mark-all-as-read' => 'v1/notification/mark-all-as-read',
                'DELETE v1/notification/<id:\d+>' => 'v1/notification/delete',

                // Questionnaire
                'GET  v1/questionnaire'         => 'v1/questionnaire/index',
                'POST v1/questionnaire'         => 'v1/questionnaire/create',
                'GET  v1/questionnaire/latest'  => 'v1/questionnaire/latest',
                'GET  v1/questionnaire/<id:\d+>' => 'v1/questionnaire/view',
                'POST v1/questionnaire/<id:\d+>' => 'v1/questionnaire/update',

                // Mood tracker
                'GET  v1/mood/today'            => 'v1/mood/today',
                'GET  v1/mood/history'          => 'v1/mood/history',
                'POST v1/mood'                  => 'v1/mood/create',

                // Support
                'GET  v1/support-ticket'        => 'v1/support-ticket/index',
                'POST v1/support-ticket'        => 'v1/support-ticket/create',
                'GET  v1/support-ticket/<id:\d+>' => 'v1/support-ticket/view',
                'POST v1/support-ticket/<id:\d+>' => 'v1/support-ticket/update',
                'POST v1/support-ticket/<id:\d+>/reply' => 'v1/support-ticket/reply',
                'POST v1/support-ticket/<id:\d+>/close' => 'v1/support-ticket/close',

                // Surveys (user)
                'GET  v1/survey/active'         => 'v1/survey/active',
                'GET  v1/survey/my-status'      => 'v1/survey/my-status',
                'POST v1/survey/respond'        => 'v1/survey/respond',

                // Admin Surveys
                'GET    v1/admin/survey'                               => 'v1/admin-survey/index',
                'POST   v1/admin/survey'                               => 'v1/admin-survey/create',
                'POST   v1/admin/survey/<id:\d+>'                      => 'v1/admin-survey/update',
                'DELETE v1/admin/survey/<id:\d+>'                      => 'v1/admin-survey/delete',
                'POST   v1/admin/survey/<id:\d+>/activate'             => 'v1/admin-survey/activate',
                'GET    v1/admin/survey/<id:\d+>/questions'            => 'v1/admin-survey/questions',
                'POST   v1/admin/survey/<id:\d+>/questions'            => 'v1/admin-survey/create-question',
                'POST   v1/admin/survey/<id:\d+>/questions/<qid:\d+>'  => 'v1/admin-survey/update-question',
                'DELETE v1/admin/survey/<id:\d+>/questions/<qid:\d+>'  => 'v1/admin-survey/delete-question',
                'GET    v1/admin/survey/<id:\d+>/results'              => 'v1/admin-survey/results',

                // OPTIONS preflight for CORS
                'OPTIONS <path:.*>' => 'v1/default/options',
            ],
        ],
    ],
    'params' => $params,
];
