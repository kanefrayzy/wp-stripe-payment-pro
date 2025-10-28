<?php

if (!defined('ABSPATH')) die();

function clean_input($input) {
    if (is_array($input)) {
        return array_map('clean_input', $input);
    }
    return preg_replace('/[\x00-\x1F\x7F]/', '', trim($input)); // מסיר תווי שליטה + רווחים מיותרים
}

/**
 * Create a subscription with limited billing cycles.
 * 
 * @since 1.0.0
 */
try {
    $currency = strtolower($this->args['currency']);
    
    // יצירת מחיר (Price) אם לא סופק
    if (empty($this->args['price_id'])) {
        $price = \Stripe\Price::create([
            'unit_amount' => $this->args['amount'],
            'currency' => $this->args['currency'],
            'recurring' => [
                'interval' => $this->args['interval'] ?? 'month',
                'interval_count' => $this->args['interval_count'] ?? 1,
            ],
            'product' => $this->args['product_id'],
        ]);
        $priceId = $price->id;
    } else {
        $priceId = $this->args['price_id'];
    }
    
    // יצירת PaymentMethod אם צריך או שימוש בקיים
    if (empty($this->args['payment_method_id'])) {
        wp_send_json_error(['message' => 'Payment method ID is required']);
        return;
    }
    
    // בדיקה אם ה-PaymentMethod שייך ללקוח, אם לא - לשייך אותו
    try {
        $paymentMethod = \Stripe\PaymentMethod::retrieve($this->args['payment_method_id']);
        if ($paymentMethod->customer !== $this->args['customer_id']) {
            $paymentMethod->attach(['customer' => $this->args['customer_id']]);
        }
    } catch (\Exception $e) {
        error_log('Payment method error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error with payment method: ' . $e->getMessage()]);
        return;
    }

    // ניקוי כל המשתנים לפני השליחה
    $clean_customer_id = clean_input($this->args['customer_id'] ?? '');
    $clean_payment_method_id = clean_input($this->args['payment_method_id'] ?? '');
    $clean_payment_page_url = filter_var(clean_input($this->page_url), FILTER_SANITIZE_URL);
    $clean_transaction_id = clean_input($this->key);
    $clean_support_agent = clean_input($this->args['support_agent'] ?? '');

    // בניית metadata נקי
    $metadata = [
        'origin' => clean_input($this->args['origin'] ?? ''),
        'app_payment_method' => 'payments',
        'support_agent' => $clean_support_agent, // ניקוי תווים בעייתיים
        'transaction_id' => $clean_transaction_id,
        'payment_page_url' => $clean_payment_page_url,
        'crm_product_id' => clean_input($this->args['crm_product_id'] ?? ''),
        'max_payments' => (int) ($this->args['max_payments'] ?? 6),
    ];

    // בדיקה שה-URL חוקי
    if (!filter_var($clean_payment_page_url, FILTER_VALIDATE_URL)) {
        wp_send_json_error(['message' => 'Invalid URL detected in payment_page_url']);
        return;
    }

    // הגדרת מספר מחזורי חיוב מקסימלי
    $max_billing_cycles = (int) ($this->args['max_payments'] ?? 6);


    // יצירת המנוי עם חיוב מיידי
    $subscription = \Stripe\Subscription::create([
        'customer' => $this->args['customer_id'],
        'items' => [
            ['price' => $priceId],
        ],
        'payment_behavior' => 'allow_incomplete', // חשוב עבור 3D Secure
        'payment_settings' => [
            'payment_method_types' => ['card'],
            'save_default_payment_method' => 'on_subscription',
        ],
        'expand' => ['latest_invoice.payment_intent'],
        //'redirect_url' => 'https://auto.gsbot.in/webhook/15061d14-2e04-4c06-a51f-1246725fa596',
        'metadata' => $metadata,
        'default_payment_method' => $this->args['payment_method_id'],
    ]);
    
    var_dump($this->args['customer_id']);
    var_dump($priceId);
    // בדיקה האם נדרשת אימות נוסף (3D Secure)
    $latestInvoice = $subscription->latest_invoice ? \Stripe\Invoice::retrieve($subscription->latest_invoice) : null;
    $paymentIntent = $latestInvoice ? $latestInvoice->payment_intent : null;

    if ($paymentIntent && $paymentIntent->status === 'requires_action' && 
        $paymentIntent->next_action && 
        $paymentIntent->next_action->type === 'use_stripe_sdk') {

        $this->setOrder('requires_action', 'limited_payments', $paymentIntent->id, $latestInvoice->id, '', $subscription->id, $this->args);
        
        wp_send_json_success([
            'requires_action' => true,
            'payment_intent_client_secret' => $paymentIntent->client_secret,
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
            'subscription_id' => $subscription->id,
            'message' => 'Payment requires additional authentication.',
        ]);
        return;
    }

    // אם התשלום הראשון הצליח, עדכון מספר המחזורים הנותרים
    if ($paymentIntent && $paymentIntent->status === 'succeeded' || $subscription->status === 'active') {
        $remaining_cycles = max(1, $max_billing_cycles - 1); // לפחות מחזור 1 נוסף

        // שימוש בפרמטר `trial_end` כדי לקבוע שהמנוי יתבטל אחרי X מחזורים
        \Stripe\Subscription::update($subscription->id, [
            'cancel_at_period_end' => false,
            'metadata' => array_merge($metadata, ['remaining_cycles' => $remaining_cycles])
        ]);


        // עדכון היסטוריית ההזמנה
        $this->setOrder('subscription_active', 'subscription', $paymentIntent->id ?? '', $latestInvoice->id ?? '', '', $subscription->id, $this->args);

        wp_send_json_success([
            'message' => 'Subscription created successfully with limited billing cycles.',
            'subscription_id' => $subscription->id,
            'payment_intent_id' => $paymentIntent->id ?? '',
            'invoice_id' => $latestInvoice->id ?? '',
            'remaining_cycles' => $remaining_cycles,
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
        ]);
    } else {
        // כישלון תשלום
        wp_send_json_error([
            'message' => 'Subscription creation failed. Status: ' . $subscription->status,
            'payment_intent_status' => $paymentIntent->status ?? 'unknown',
        ]);
    }

} catch (\Stripe\Exception\ApiErrorException $e) {
    $this->setOrder('error', 'subscription', null, null, $e->getMessage(), $this->args);
    error_log('Subscription processing error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Error processing subscription: ' . $e->getMessage()]);

} catch (Exception $e) {
    $this->setOrder('error', 'subscription', null, null, $e->getMessage(), $this->args);
    error_log('Subscription processing error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Error processing subscription: ' . $e->getMessage()]);
}