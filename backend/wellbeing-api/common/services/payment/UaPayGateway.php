<?php

namespace common\services\payment;

use common\contracts\PaymentGatewayInterface;
use common\models\AppSettings;

class UaPayGateway implements PaymentGatewayInterface
{
    private string $merchantKey;
    private string $secretKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->merchantKey = AppSettings::get('uapay_merchant_key');
        $this->secretKey   = AppSettings::get('uapay_secret_key');
        $this->apiUrl      = rtrim(AppSettings::get('uapay_api_url', 'https://api.uapay.ua'), '/');
    }

    public function createPayment(array $order): array
    {
        $payload = [
            'amount'      => (int)round($order['amount'] * 100), // копійки
            'currency'    => $order['currency'] ?? 'UAH',
            'description' => $order['description'] ?? 'Оплата консультації',
            'orderId'     => $order['order_id'],
            'callbackUrl' => $order['callback_url'],
            'resultUrl'   => $order['return_url'],
        ];

        $response = $this->request('POST', '/api/v1/checkout/create', $payload);

        return [
            'checkout_url' => $response['checkoutUrl'] ?? $response['checkout_url'] ?? '',
            'order_id'     => $order['order_id'],
        ];
    }

    public function verifyCallback(array $data): bool
    {
        $received  = $data['signature'] ?? '';
        $orderId   = $data['orderId']   ?? $data['order_id'] ?? '';
        $amount    = $data['amount']    ?? '';
        $status    = $data['status']    ?? '';

        $expected = hash_hmac('sha256', $orderId . $amount . $status, $this->secretKey);
        return hash_equals($expected, $received);
    }

    public function parseCallback(array $data): array
    {
        $status = strtolower($data['status'] ?? '');
        return [
            'order_id'   => $data['orderId']    ?? $data['order_id'] ?? '',
            'payment_id' => $data['paymentId']  ?? $data['payment_id'] ?? '',
            'status'     => in_array($status, ['success', 'paid', 'approved']) ? 'success' : 'failure',
            'amount'     => isset($data['amount']) ? round((float)$data['amount'] / 100, 2) : 0.0,
        ];
    }

    public function getStatus(string $orderId): array
    {
        $response = $this->request('GET', "/api/v1/orders/{$orderId}");
        $status   = strtolower($response['status'] ?? '');
        return [
            'status'     => in_array($status, ['success', 'paid', 'approved']) ? 'success' : ($status === 'pending' ? 'pending' : 'failure'),
            'payment_id' => $response['paymentId'] ?? '',
        ];
    }

    public function refund(string $gatewayOrderId, float $amount, string $description): bool
    {
        $response = $this->request('POST', '/api/v1/refunds', [
            'orderId'     => $gatewayOrderId,
            'amount'      => (int)round($amount * 100),
            'description' => $description,
        ]);

        $status = strtolower($response['status'] ?? '');
        // UaPay may return 200 with status pending (async) — treat as success
        return isset($response['id']) || in_array($status, ['success', 'pending', 'processing']);
    }

    private function request(string $method, string $path, array $body = []): array
    {
        $ch = curl_init($this->apiUrl . $path);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->merchantKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $raw = curl_exec($ch);
        curl_close($ch);

        return json_decode($raw ?: '{}', true) ?? [];
    }
}
