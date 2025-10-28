<?php

if (!defined('ABSPATH')) die();

try {
    $currency = strtolower($this->args['currency']);
    
    // יצירת מחיר (Price) אם לא סופק
    if (empty($this->args['price_id'])) {
        $price = \Stripe\Price::create([
            'unit_amount' => $this->args['amount'],
            'currency' => $this->args['currency'],
            'recurring' => [
                'interval' => $this->args['interval'] ?? 'month', // month, year, week, day
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
        // צריך ליצור קודם payment method בצד הלקוח ולהעביר את ה-ID
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
        // אם PaymentMethod לא קיים או יש שגיאה בשיוך
        error_log('Payment method error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error with payment method: ' . $e->getMessage()]);
        return;
    }
    
    // הגדרת ה-metadata למנוי
    $metadata = [
        'origin' => $this->args['origin'] ?? '',
        'app_payment_method' => 'subscription',
        'support_agent' => $this->args['support_agent'] ?? '',
        'transaction_id' => $this->key,
        'payment_page_url' => $this->page_url,
        'crm_product_id' => $this->args['crm_product_id'] ?? '',
        'max_payments' => $this->args['max_payments'] ?? '',
    ];
    
    // יצירת המנוי עם הגדרות תשלום
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
    
    // בדיקה האם נדרשת אימות נוסף (3D Secure)
    $latestInvoice = $subscription->latest_invoice;
    $paymentIntent = $latestInvoice->payment_intent;

    //var_dump($paymentIntent->status,$paymentIntent->next_action);
    //var_dump($paymentIntent->next_action);
    //var_dump($paymentIntent->next_action->type);
    
    // חשוב!!!! 'use_stripe_sdk' זה עבור 3D Secure
    if ($paymentIntent->status === 'requires_action' && 
        $paymentIntent->next_action && 
        $paymentIntent->next_action->type === 'use_stripe_sdk') {

           
        // רישום המצב הנוכחי של המנוי במערכת
        $this->setOrder('requires_action', 'subscription', $paymentIntent->id, $latestInvoice->id, '', $subscription->id,$this->args);
        
        // החזרת פרטי ה-redirect לצד הלקוח
        wp_send_json_success([
            'requires_action' => true,
            'payment_intent_client_secret' => $paymentIntent->client_secret,
            //'redirect_url' => $paymentIntent->next_action->redirect_to_url->url,
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
            'subscription_id' => $subscription->id,
            'message' => 'Payment requires additional authentication.',
        ]);
    } elseif ($paymentIntent->status === 'succeeded' || $subscription->status === 'active') {
        // התשלום הצליח, המנוי פעיל
        
        // עדכון metadata של החשבונית
        $invoice = \Stripe\Invoice::retrieve($latestInvoice->id);
        $invoice->metadata = $metadata;
        $invoice->save();
        
        // רישום ההצלחה במערכת
        $this->setOrder('subscription_active', 'subscription', $paymentIntent->id, $latestInvoice->id, '', $subscription->id,$this->args);
        
        wp_send_json_success([
            'message' => 'Subscription created successfully.',
            'subscription_id' => $subscription->id,
            'payment_intent_id' => $paymentIntent->id,
            'invoice_id' => $latestInvoice->id,
            'thank_you_page' => $this->args['thank_you_page'] ?? '',
        ]);
    } else {
        // מצב אחר - בדרך כלל כישלון התשלום
        wp_send_json_error([
            'message' => 'Subscription creation failed. Status: ' . $subscription->status,
            'payment_intent_status' => $paymentIntent->status,
        ]);
    }
} catch (\Stripe\Exception\ApiErrorException $e) {
    // טיפול בשגיאות API של Stripe
    $this->setOrder('error', 'subscription', null, null, $e->getMessage(),$this->args);
    error_log('Subscription processing error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Error processing subscription: ' . $e->getMessage()]);
} catch (Exception $e) {
    // טיפול בשגיאות כלליות
    $this->setOrder('error', 'subscription', null, null, $e->getMessage(),$this->args);
    error_log('Subscription processing error: ' . $e->getMessage());
    wp_send_json_error(['message' => 'Error processing subscription: ' . $e->getMessage()]);
}