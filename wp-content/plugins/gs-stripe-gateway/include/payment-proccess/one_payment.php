<?php

if (!defined('ABSPATH')) die();

try {
    $currency = strtolower($this->args['currency']);
    
    // Генерация idempotency key для защиты от дублирования
    $idempotency_key = 'payment_' . $this->args['customer_id'] . '_' . time() . '_' . $this->key;
    
    // Подготовка metadata с product_id и price_id
    $metadata = [
        'product_id' => $this->args['product'] ?? '',
        'price_id' => $this->args['price_id'] ?? '',
        'origin' => $this->args['origin'] ?? '',
        'app_payment_method' => 'one_time',
        'support_agent' => $this->args['support_agent'] ?? '',
        'transaction_id' => $this->key,
        'payment_page_url' => $this->page_url,
        'crm_product_id' => $this->args['crm_product_id'] ?? '',
    ];
    
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $this->args['amount'],
        'currency' => $this->args['currency'],
        'customer' => $this->args['customer_id'],
        'payment_method' => $this->args['payment_method_id'],
        'confirmation_method' => 'automatic',
        'return_url' => $this->args['thank_you_page'],
        'confirm' => true,
        'setup_future_usage' => 'off_session',
        'metadata' => $metadata,
        'description' => 'Payment for product: ' . ($this->args['product'] ?? 'N/A'),
    ], [
        'idempotency_key' => $idempotency_key
    ]);
    
    // Проверка статуса платежа
    if ($paymentIntent->status === 'requires_action') {
        // Требуется дополнительное действие (3D Secure или другая аутентификация)
        $this->setOrder('requires_action', 'one_payment', $paymentIntent->id);
        wp_send_json_success([
            'requires_action' => true,
            'payment_intent_client_secret' => $paymentIntent->client_secret,
            'message' => 'Payment requires additional authentication.',
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
        ]);
    } elseif ($paymentIntent->status === 'succeeded') {
        // ✅ ИСПРАВЛЕНО: Убрано создание Invoice - платеж уже прошел через PaymentIntent
        // Записываем успешный платеж без повторного списания
        $this->setOrder('payment_succeeded', 'one_payment', $paymentIntent->id);
        
        wp_send_json_success([
            'message' => 'Payment succeeded.',
            'payment_intent_id' => $paymentIntent->id,
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
        ]);
    } else {
        wp_send_json_error(['message' => 'Payment failed. Status: ' . $paymentIntent->status]);
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    $this->setOrder('error', 'one_payment', null, null, $e->getMessage());
    error_log('Payment processing error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Error processing payment: ' . $e->getMessage()]);
} catch (Exception $e) {
    $this->setOrder('error', 'one_payment', null, null, $e->getMessage());
    error_log('Payment processing error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Error processing payment: ' . $e->getMessage()]);
}