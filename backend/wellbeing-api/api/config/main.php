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
                // Companies (public)
                'GET  v1/company'               => 'v1/company/index',

                // Auth
                'POST v1/user/login'            => 'v1/user/login',
                'POST v1/user/register'         => 'v1/user/register',
                'POST v1/user/logout'           => 'v1/user/logout',
                'GET  v1/user/profile'          => 'v1/user/profile',
                'POST v1/user/update-profile'   => 'v1/user/update-profile',

                // Dashboard
                'GET v1/dashboard'              => 'v1/dashboard/index',

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
                'POST v1/document'              => 'v1/document/create',
                'GET  v1/document/<id:\d+>'     => 'v1/document/view',
                'DELETE v1/document/<id:\d+>'   => 'v1/document/delete',

                // Payments
                'GET  v1/payment'               => 'v1/payment/index',
                'POST v1/payment'               => 'v1/payment/create',
                'GET  v1/payment/<id:\d+>'      => 'v1/payment/view',
                'POST v1/payment/<id:\d+>/process' => 'v1/payment/process',

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

                // Support
                'GET  v1/support-ticket'        => 'v1/support-ticket/index',
                'POST v1/support-ticket'        => 'v1/support-ticket/create',
                'GET  v1/support-ticket/<id:\d+>' => 'v1/support-ticket/view',
                'POST v1/support-ticket/<id:\d+>' => 'v1/support-ticket/update',
                'POST v1/support-ticket/<id:\d+>/reply' => 'v1/support-ticket/reply',
                'POST v1/support-ticket/<id:\d+>/close' => 'v1/support-ticket/close',

                // OPTIONS preflight for CORS
                'OPTIONS <path:.*>' => 'v1/default/options',
            ],
        ],
    ],
    'params' => $params,
];
