<?php

namespace common\contracts;

interface PaymentGatewayInterface
{
    /**
     * Create a payment order at the gateway.
     * Returns ['checkout_url' => string, 'order_id' => string]
     */
    public function createPayment(array $order): array;

    /**
     * Verify that the callback payload came from the real gateway (signature check).
     */
    public function verifyCallback(array $data): bool;

    /**
     * Parse the callback into a normalized structure.
     * Returns ['order_id' => string, 'payment_id' => string, 'status' => 'success'|'failure', 'amount' => float]
     */
    public function parseCallback(array $data): array;

    /**
     * Query current payment status from the gateway directly.
     * Returns ['status' => 'success'|'pending'|'failure', 'payment_id' => string]
     */
    public function getStatus(string $orderId): array;
}
