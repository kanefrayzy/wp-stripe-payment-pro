<?php



require 'vendor/autoload.php';

$stripe = new \Stripe\StripeClient([
  "api_key" => 'sk_test_51QBuMWGlRmp3igmYHCvjwdyN5udSOW0Ci4l9olHh9ODD81GXap28qbzTYdlehaBXg2pDR5V1yTTctWszJTuhf4kJ007O1QGUBJ'
]);

$checkout_session = $stripe->checkout->sessions->create([
  'line_items' => [[
    'price_data' => [
      'currency' => 'usd',
      'product_data' => [
        'name' => 'T-shirt',
      ],
      'unit_amount' => 2000,
    ],
    'quantity' => 1,
  ]],
  'mode' => 'payment',
  'ui_mode' => 'embedded',
  'return_url' => 'https://example.com/checkout/return?session_id={CHECKOUT_SESSION_ID}',
]);

  echo json_encode(array('clientSecret' => $checkout_session->client_secret));

 
?>

