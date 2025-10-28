<?php
require 'vendor/autoload.php';

\Stripe\Stripe::setApiKey('rk_test_51QBuMWGlRmp3igmYbgdjQzsp92XjcyXx5jtAo0hv44HIWaugAaRMTGuq4GGepMHlDr3BgYKGCGIH9v5WIiNx6Ys000FEra68UD');

$json_str = file_get_contents('php://input');
$json_obj = json_decode($json_str);



try {
    $paymentIntent = \Stripe\PaymentIntent::create([
        'amount' => 1000, // Adjust amount as needed
        'currency' => 'usd',
        'payment_method' => $json_obj->payment_method,
        'confirmation_method' => 'manual',
        'confirm' => true,
        'metadata' => [
            'coupon' => $json_obj->coupon
        ],
        'setup_future_usage' => 'off_session', // For future payments
    ]);

    echo json_encode(['client_secret' => $paymentIntent->client_secret]);
} catch (Error $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
