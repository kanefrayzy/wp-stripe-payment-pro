<?php

if (!defined('ABSPATH')) die();

try {
    $currency = strtolower($this->args['currency']);
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => $this->args['amount'],
        'currency' => $this->args['currency'],
        'customer' => $this->args['customer_id'],
        'payment_method' => $this->args['payment_method_id'],
        'confirmation_method' => 'automatic',
        'return_url' => $this->args['thank_you_page'],
        'confirm' => true,
        'setup_future_usage' => 'off_session',
    ]);
    
    if ($paymentIntent->status === 'requires_action' && $paymentIntent->next_action->type === 'redirect_to_url') {
        $this->setOrder('requires_action', 'one_payment', $paymentIntent->id);
        wp_send_json_success([
            'requires_action' => true,
            'payment_intent_client_secret' => $paymentIntent->client_secret,
            'redirect_url' => $paymentIntent->next_action->redirect_to_url->url,
            'message' => 'Payment requires additional authentication.',
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
        ]);
    } elseif ($paymentIntent->status === 'succeeded') {
        // Create the invoice item first
        $invoiceItem = \Stripe\InvoiceItem::create([
            'customer' => $this->args['customer_id'],
            'amount' => $this->args['amount'],
            'currency' => $this->args['currency'],
            'description' => 'Payment for service',
        ]);
        
        // Create the invoice with pending_invoice_items_behavior set to include
        $invoice = \Stripe\Invoice::create([
            'customer' => $this->args['customer_id'],
            'collection_method' => 'charge_automatically',
            'auto_advance' => true,
            'pending_invoice_items_behavior' => 'include', // Include pending items
            'metadata' => [
                'origin' => $this->args['origin'],
                'app_payment_method'=> 'one_time',
                'support_agent'    => $this->args['support_agent'],
                'transaction_id'   => $this->key,
                'payment_page_url' => $this->page_url,
                'crm_product_id'   => $this->args['crm_product_id'] ?? '',
            ],
        ]);
        
        // Properly finalize and pay the invoice using instance methods
        $invoice->finalizeInvoice();
        $invoice->pay();
        
        // Record the successful payment
        $this->setOrder('payment_succeeded', 'one_payment', $paymentIntent->id, $invoice->id);
        
        wp_send_json_success([
            'message' => 'Payment succeeded.',
            'payment_intent_id' => $paymentIntent->id,
            'invoice_id' => $invoice->id,
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