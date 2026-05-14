<?php

namespace api\modules\v1\controllers;

use common\models\AppSettings;
use Yii;
use yii\rest\Controller;

class PortalSettingsController extends Controller
{
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        unset($behaviors['authenticator']);
        return $behaviors;
    }

    public function actionIndex()
    {
        Yii::$app->response->format = 'json';

        return [
            'site_title_prefix' => AppSettings::get('site_title_prefix', 'Wellbeing'),
            'company_name'      => AppSettings::get('company_name', ''),
            'favicon_url'       => AppSettings::get('favicon_url', ''),
            'timezone'          => AppSettings::get('timezone', 'Europe/Kyiv'),
            'default_locale'    => AppSettings::get('default_locale', 'uk'),
            'terms_of_service_uk' => AppSettings::get('terms_of_service_uk', ''),
            'terms_of_service_en' => AppSettings::get('terms_of_service_en', ''),
            'privacy_policy_uk'   => AppSettings::get('privacy_policy_uk', ''),
            'privacy_policy_en'   => AppSettings::get('privacy_policy_en', ''),
            'support_phone'     => AppSettings::get('support_phone', ''),
            'support_viber_url' => AppSettings::get('support_viber_url', ''),
            'support_tg_url'    => AppSettings::get('support_tg_url', ''),
        ];
    }
}
