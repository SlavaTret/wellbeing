<?php

namespace common\services\payment;

use common\contracts\PaymentGatewayInterface;
use common\models\AppSettings;
use Yii;

class LiqPayGateway implements PaymentGatewayInterface
{
    private const CHECKOUT_URL = 'https://www.liqpay.ua/api/3/checkout';
    private const API_URL      = 'https://www.liqpay.ua/api/request';

    private string $publicKey;
    private string $privateKey;

    public function __construct()
    {
        $this->publicKey  = AppSettings::get('liqpay_public_key');
        $this->privateKey = AppSettings::get('liqpay_private_key');
    }

    public function createPayment(array $order): array
    {
        $params = [
            'version'     => 3,
            'public_key'  => $this->publicKey,
            'action'      => 'pay',
            'amount'      => number_format((float)$order['amount'], 2, '.', ''),
            'currency'    => $order['currency'] ?? 'UAH',
            'description' => $order['description'] ?? 'Оплата консультації',
            'order_id'    => $order['order_id'],
            'result_url'  => $order['return_url'],
            'server_url'  => $order['callback_url'],
            'language'    => 'uk',
        ];

        $data      = base64_encode(json_encode($params));
        $signature = $this->sign($data);

        return [
            'checkout_url' => self::CHECKOUT_URL . '?data=' . urlencode($data) . '&signature=' . urlencode($signature),
            'order_id'     => $order['order_id'],
        ];
    }

    public function verifyCallback(array $data): bool
    {
        $receivedSig = $data['signature'] ?? '';
        $base64Data  = $data['data']      ?? '';
        $expected    = $this->sign($base64Data);
        return hash_equals($expected, $receivedSig);
    }

    public function parseCallback(array $data): array
    {
        $decoded = json_decode(base64_decode($data['data'] ?? ''), true) ?? [];
        $status  = strtolower($decoded['status'] ?? '');

        return [
            'order_id'   => $decoded['order_id']    ?? '',
            'payment_id' => (string)($decoded['payment_id'] ?? ''),
            'status'     => in_array($status, ['success', 'sandbox']) ? 'success' : 'failure',
            'amount'     => (float)($decoded['amount'] ?? 0),
        ];
    }

    public function getStatus(string $orderId): array
    {
        $params = [
            'version'    => 3,
            'public_key' => $this->publicKey,
            'action'     => 'status',
            'order_id'   => $orderId,
        ];

        $data     = base64_encode(json_encode($params));
        $response = $this->apiRequest(['data' => $data, 'signature' => $this->sign($data)]);
        $status   = strtolower($response['status'] ?? '');

        return [
            'status'     => in_array($status, ['success', 'sandbox']) ? 'success' : ($status === 'processing' ? 'pending' : 'failure'),
            'payment_id' => (string)($response['payment_id'] ?? ''),
        ];
    }

    public function refund(string $gatewayOrderId, float $amount, string $description): bool
    {
        $params = [
            'version'    => 3,
            'public_key' => $this->publicKey,
            'action'     => 'refund',
            'order_id'   => $gatewayOrderId,
            'amount'     => number_format($amount, 2, '.', ''),
            'currency'   => 'UAH',
            'description'=> $description,
        ];

        $data     = base64_encode(json_encode($params));
        $response = $this->apiRequest(['data' => $data, 'signature' => $this->sign($data)]);
        $status   = strtolower($response['status'] ?? '');

        Yii::warning('LiqPay refund response for order ' . $gatewayOrderId . ': ' . json_encode($response), 'payment');

        return in_array($status, ['reversed', 'success', 'sandbox']);
    }

    private function sign(string $data): string
    {
        return base64_encode(sha1($this->privateKey . $data . $this->privateKey, true));
    }

    private function apiRequest(array $params): array
    {
        $ch = curl_init(self::API_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        $raw = curl_exec($ch);
        curl_close($ch);
        return json_decode($raw ?: '{}', true) ?? [];
    }
}
