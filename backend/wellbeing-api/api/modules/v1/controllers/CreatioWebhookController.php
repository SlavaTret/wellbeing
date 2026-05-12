<?php

namespace api\modules\v1\controllers;

use common\models\AppSettings;
use common\services\CreatioSyncService;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Receives inbound webhooks from Creatio and syncs data into the portal.
 *
 * Authentication: shared secret in X-Webhook-Secret header (or 'secret' query param).
 * Set 'creatio_webhook_secret' in app_settings to enable verification.
 * If the setting is empty, any request is accepted (dev mode).
 */
class CreatioWebhookController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors(): array
    {
        return []; // no auth middleware — authenticated via shared secret below
    }

    /**
     * POST /v1/creatio/webhook/contract
     *
     * Creatio sends the full WelContract OData record on create/update.
     * Expected payload example:
     * {
     *   "Id": "...",
     *   "WelAccountId": "...",
     *   "WelName": "...",
     *   "WelStartDate": "2025-05-01T00:00:00Z",
     *   "WelEndDate": "2025-05-31T00:00:00Z",
     *   "WelPriceConsultation": 1000.0,
     *   "WelLimitConsultationsEmployee": 3,
     *   "WelActive": true
     * }
     */
    public function actionContract(): array
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        if (!$this->verifySecret()) {
            Yii::$app->response->statusCode = 401;
            return ['error' => 'Unauthorized'];
        }

        $body = Yii::$app->request->rawBody;
        $data = json_decode($body, true);

        if (empty($data['Id']) || empty($data['WelAccountId'])) {
            Yii::$app->response->statusCode = 422;
            return ['error' => 'Missing required fields: Id, WelAccountId'];
        }

        Yii::warning('[Webhook] contract received id=' . $data['Id'] . ' account=' . $data['WelAccountId'], 'creatio');

        $service = new CreatioSyncService();
        $service->upsertContractPayload($data);

        return ['ok' => true];
    }

    private function verifySecret(): bool
    {
        $expected = AppSettings::get('creatio_webhook_secret', '');
        if ($expected === '') {
            return true; // dev mode — no secret configured
        }

        $provided = Yii::$app->request->headers->get('X-Webhook-Secret')
                 ?? Yii::$app->request->get('secret', '');

        return hash_equals($expected, $provided);
    }
}
