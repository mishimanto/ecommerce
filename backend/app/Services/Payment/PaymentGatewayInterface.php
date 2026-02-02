<?php

namespace App\Services\Payment;

interface PaymentGatewayInterface
{
    /**
     * Initialize payment
     */
    public function initializePayment($order, $paymentData = []);

    /**
     * Process payment
     */
    public function processPayment($paymentData);

    /**
     * Verify payment
     */
    public function verifyPayment($transactionId);

    /**
     * Refund payment
     */
    public function refundPayment($transactionId, $amount = null, $reason = null);

    /**
     * Check payment status
     */
    public function checkPaymentStatus($transactionId);

    /**
     * Handle webhook
     */
    public function handleWebhook($payload);
}