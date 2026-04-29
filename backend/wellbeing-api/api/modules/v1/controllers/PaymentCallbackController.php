<?php

namespace api\modules\v1\controllers;

use common\services\PaymentService;
use Yii;
use yii\rest\Controller;

/**
 * Public controller — no auth required.
 * Handles POST callbacks from UaPay / LiqPay.
 */
class PaymentCallbackController extends Controller
{
    public function behaviors()
    {
        // Remove auth — callbacks come from the payment gateway, not the user
        $behaviors = parent::behaviors();
        unset($behaviors['authenticator']);
        return $behaviors;
    }

    /** POST /v1/payment/callback/<gateway> */
    public function actionHandle($gateway)
    {
        Yii::$app->response->format = 'json';

        $allowed = ['liqpay', 'uapay'];
        if (!in_array($gateway, $allowed)) {
            Yii::$app->response->statusCode = 400;
            return ['error' => 'Unknown gateway'];
        }

        // LiqPay sends multipart POST with 'data' and 'signature' fields
        // UaPay sends JSON body — both are accepted via request->post()
        $data = Yii::$app->request->post();
        if (empty($data) && $raw = Yii::$app->request->rawBody) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        try {
            (new PaymentService())->handleCallback($gateway, $data);
        } catch (\RuntimeException $e) {
            Yii::$app->response->statusCode = 400;
            return ['error' => $e->getMessage()];
        } catch (\Throwable $e) {
            Yii::error('Payment callback error: ' . $e->getMessage(), 'payment');
            Yii::$app->response->statusCode = 500;
            return ['error' => 'Internal error'];
        }

        return ['ok' => true];
    }
}
