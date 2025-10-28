<?php
require 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('YOUR_STRIPE_SECRET_KEY');

$json_str = file_get_contents('php://input');
$json_obj = json_decode($json_str);

try {
    $customer = \Stripe\Customer::create([
        'payment_method' => $json_obj->payment_method,
        'email' => $json_obj->billing_details->email,
        'invoice_settings' => [
            'default_payment_method' => $json_obj->payment_method,
        ],
    ]);

    $subscription = \Stripe\Subscription::create([
        'customer' => $customer->id,
        'items' => [['price' => 'YOUR_PRICE_ID']],
        'coupon' => $json_obj->coupon,
        'expand' => ['latest_invoice.payment_intent'],
    ]);

    echo json_encode($subscription);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
