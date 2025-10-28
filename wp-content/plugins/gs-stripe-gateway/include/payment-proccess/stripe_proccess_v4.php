<?php
if (!defined('ABSPATH')) die();

class StripePaymentProcessor {
    private $stripe;
    private $args;

    public function __construct($args) {
        $this->args = $args;
        $this->key = bin2hex(random_bytes(16));
        $this->page_url = get_permalink($this->args['page_id']);
        //$this->crm_product_id = get_field('crm_product_id',$this->args['page_id']);
        $this->initializeStripe();
    }

    private function initializeStripe() {
        $testmode = get_post_meta($this->args['page_id'], 'env_mode', true);
        $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
        
        \Stripe\Stripe::setApiKey($frkey);
        $this->stripe = new \Stripe\StripeClient($frkey);
    }

    public function processPayment() {
        $this->validateInput();
        $customer_id = $this->getOrCreateCustomer();
        $this->attachPaymentMethod($customer_id);

        $this->args['customer_id'] = $customer_id;
        //var_dump($this->args['total_payments']);
        //var_dump($this->args['price_id']);
        //var_dump($this->args['product']);

        if ($this->args['max_payments'] == null) {
            //echo 'subscription';
            return $this->subscription();
        } elseif($this->args['max_payments'] == 1) {
            //echo 'onePayment';
            return $this->onePayment();   
        } else {
            //echo 'limitedPayments';
            return $this->limitedPayments();
        }
        
    }

    private function validateInput() {
        if (empty($this->args['email']) || empty($this->args['name']) || empty($this->args['phone'])) {
            wp_send_json_error(["message" => 'mandatory fields missing', "color" => "red"]);
        }

        $product = $this->stripe->products->retrieve($this->args['product'], []);
        if (!isset($product)) {
            wp_send_json_error(["message" => 'No Product ID', "color" => "red"]);
            wp_die();
        }
        

        $this->args['seleced_price'] = $this->stripe->prices->retrieve($this->args['price_id'], []);
        $max_payments_limit = $product->metadata->payment_limit ?? '';

        if ($this->args['max_payments'] > $max_payments_limit) {
            wp_send_json_error(["message" => "מקסימום $max_payments_limit תשלומים מורשים", "color" => "red"]);
            exit;
        }

        if ($this->args['seleced_price']->active != true) {
            wp_send_json_error(["message" => "המחיר לא פעיל בסטרייפ", "color" => "red"]);
            exit;
        }

        // Set amount and currency
        $this->args['amount'] = $this->args['seleced_price']->unit_amount;
        $this->args['currency'] = $this->args['seleced_price']->currency;
    }

    private function getOrCreateCustomer() {
        try {
            $customers = \Stripe\Customer::all(['email' => $this->args['email']]);
            if (count($customers->data) > 0) {
                return $customers->data[0]->id;
            } else {
                $customer = \Stripe\Customer::create([
                    'email' => $this->args['email'],
                    'name' => "{$this->args['name']} {$this->args['lname']}",
                    'phone' => $this->args['phone'],
                ]);
                return $customer->id;
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            wp_send_json_error(['message' => 'Error creating or retrieving customer: ' . $e->getMessage()]);
            return null;
        }
    }

    private function attachPaymentMethod($customer_id) {
        try {
            $this->stripe->paymentMethods->attach(
                $this->args['payment_method_id'],
                ['customer' => $customer_id]
            );

            $this->stripe->customers->update($customer_id, [
                'invoice_settings' => ['default_payment_method' => $this->args['payment_method_id']],
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            wp_send_json_error(['message' => 'Error attaching payment method: ' . $e->getMessage()]);
            return;
        }
    }

    private function onePayment() {
        // Проверка использования Invoice вместо PaymentIntent
        $use_invoice = get_post_meta($this->args['page_id'], 'use_invoice', true);
        
        if ($use_invoice === '1') {
            return $this->createInvoice();
        } else {
            require_once ('one_payment.php');
        }
    }

    private function limitedPayments() {
        require_once ('limited_payments.php');
        //require_once ('limited_billing_cycle.php');
    }

    private function subscription() {
        require_once ('subscription.php');
    }

    private function createInvoice() {
        try {
            $idempotency_key = 'invoice_' . $this->args['customer_id'] . '_' . time() . '_' . $this->key;
            
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
            
            $invoiceItem = \Stripe\InvoiceItem::create([
                'customer' => $this->args['customer_id'],
                'price' => $this->args['price_id'],
                'quantity' => 1,
            ]);

            $invoice = \Stripe\Invoice::create([
                'customer' => $this->args['customer_id'],
                'collection_method' => 'charge_automatically',
                'default_payment_method' => $this->args['payment_method_id'],
                'metadata' => $metadata,
                'auto_advance' => true,
            ]);

            $finalizedInvoice = $invoice->finalizeInvoice();
            
            if ($finalizedInvoice->status === 'open') {
                $paidInvoice = $finalizedInvoice->pay();
            } else {
                $paidInvoice = $finalizedInvoice;
            }

            $retrievedInvoice = \Stripe\Invoice::retrieve($paidInvoice->id, ['expand' => ['payment_intent']]);

            if ($retrievedInvoice->payment_intent) {
                $paymentIntent = $retrievedInvoice->payment_intent;
                
                if (is_string($paymentIntent)) {
                    $paymentIntent = \Stripe\PaymentIntent::retrieve($paymentIntent);
                }
                
                if ($paymentIntent->status === 'requires_action') {
                    $this->setOrder('requires_action', 'one_payment', $paymentIntent->id, $retrievedInvoice->id);
                    
                    wp_send_json_success([
                        'requires_action' => true,
                        'payment_intent_client_secret' => $paymentIntent->client_secret,
                        'message' => 'Invoice requires additional authentication.',
                        'thank_you_page' => $this->args['thank_you_page'] ?? '',
                    ]);
                } elseif ($paymentIntent->status === 'succeeded') {
                    $this->setOrder('payment_succeeded', 'one_payment', $paymentIntent->id, $retrievedInvoice->id);
                    
                    wp_send_json_success([
                        'message' => 'Invoice paid successfully.',
                        'invoice_id' => $retrievedInvoice->id,
                        'payment_intent_id' => $paymentIntent->id,
                        'thank_you_page' => $this->args['thank_you_page'] ?? '',
                    ]);
                } else {
                    wp_send_json_error(['message' => 'Payment failed. Status: ' . $paymentIntent->status]);
                }
            } elseif ($retrievedInvoice->status === 'paid') {
                $this->setOrder('payment_succeeded', 'one_payment', 'invoice_' . $retrievedInvoice->id, $retrievedInvoice->id);
                
                wp_send_json_success([
                    'message' => 'Invoice paid successfully.',
                    'invoice_id' => $retrievedInvoice->id,
                    'thank_you_page' => $this->args['thank_you_page'] ?? '',
                ]);
            } else {
                wp_send_json_error(['message' => 'No payment intent found for invoice. Status: ' . $retrievedInvoice->status]);
            }
        } catch (\Stripe\Exception\ApiErrorException $e) {
            $this->setOrder('error', 'one_payment', null, null, $e->getMessage());
            error_log('Invoice processing error: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error processing invoice: ' . $e->getMessage()]);
        }
    }

    private function setOrder($status, $payment_type, $paymentIntent = null, $invoice_id = null, $error = null, $subscription_id = null,$args = null) {
        
        $title = $payment_type === 'subscription' ? 'הו"ק' : $paymentIntent;
        if (!empty($error)) {
            $title = $error;
        }

        $order_post = [
            'post_title' => $title,
            'post_content' => 'Payment Intent ID: ' . $paymentIntent,
            'post_status' => 'publish',
            'post_type' => $payment_type === 'subscription' ? 'subscription' : 'order',
        ];
        $id = wp_insert_post($order_post);


        $customer_id = $this->args['customer_id'] ?? '';
        $amount = $this->args['amount'] ?? '';
        
        update_post_meta($id, 'customer_name', sanitize_text_field($customer_id));
        update_post_meta($id, 'subscription_id', sanitize_text_field($subscription_id));
        update_post_meta($id, 'payment_intent_id', sanitize_text_field($paymentIntent));
        update_post_meta($id, 'amount', sanitize_text_field($amount / 100));
        update_post_meta($id, 'status', sanitize_text_field($status));
        update_post_meta($id, 'status', sanitize_text_field($status));
        update_post_meta($id, 'notes', sanitize_text_field($error));
        
        
        
        //var_dump( $status ); 
        //var_dump( $payment_type ); 
        return $id;
    }
}

function stripe_process_payment_action() {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }

    if (!isset($data['nonce1']) || !wp_verify_nonce($data['nonce1'], 'stripe_payment_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }

    

    $args = [
        'page_id' => isset($data['page_id']) ? intval($data['page_id']) : 0,
        'product' => sanitize_text_field(get_post_meta($data['page_id'], 'product', true)),
        'max_payments' => isset($data['payments']) ? sanitize_text_field($data['payments']) : '',
        'email' => isset($data['email']) ? sanitize_email($data['email']) : '',
        'name' => isset($data['firstName']) ? sanitize_text_field($data['firstName']) : '',
        'lname' => isset($data['lastName']) ? sanitize_text_field($data['lastName']) : '',
        'phone' => isset($data['phone']) ? sanitize_text_field($data['phone']) : '',
        'price_id' => isset($data['price_id']) ? sanitize_text_field($data['price_id']) : '',
        'support_agent' => isset($data['support_agent']) ? sanitize_text_field($data['support_agent']) : '',
        'thank_you_page' => esc_url(get_post_meta($data['page_id'], 'thankyou', true)),
        'coupon_code' => isset($data['coupon']) ? sanitize_text_field($data['coupon']) : '',
        'payment_method_id' => isset($data['payment_method_id']) ? sanitize_text_field($data['payment_method_id']) : '',
        'crm_product_id'    => get_post_meta($data['page_id'], 'crm_product_id', true),
        'origin' => 'API_VER2',
    ];

    if (empty($args['thank_you_page'])) {
        $args['thank_you_page'] = home_url('/thank-you');
    }

    $processor = new StripePaymentProcessor($args);
    $processor->processPayment();

    wp_die();
}

add_action('wp_ajax_stripe_process_payment', 'stripe_process_payment_action');
add_action('wp_ajax_nopriv_stripe_process_payment', 'stripe_process_payment_action');

function stripe_confirm_payment_action() {
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        $data = $_POST;
    }

    if (!isset($data['nonce1']) || !wp_verify_nonce($data['nonce1'], 'stripe_payment_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }

    $payment_intent_id = isset($data['payment_intent_id']) ? sanitize_text_field($data['payment_intent_id']) : '';
    $page_id = isset($data['page_id']) ? intval($data['page_id']) : 0;

    if (empty($payment_intent_id)) {
        wp_send_json_error(['message' => 'Payment Intent ID is required']);
        wp_die();
    }

    try {
        $testmode = get_post_meta($page_id, 'env_mode', true);
        $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
        \Stripe\Stripe::setApiKey($frkey);

        $paymentIntent = \Stripe\PaymentIntent::retrieve($payment_intent_id);

        if ($paymentIntent->status === 'succeeded') {
            $thank_you_page = esc_url(get_post_meta($page_id, 'thankyou', true));
            if (empty($thank_you_page)) {
                $thank_you_page = home_url('/thank-you');
            }

            wp_send_json_success([
                'message' => 'Payment confirmed successfully.',
                'payment_intent_id' => $paymentIntent->id,
                'thank_you_page' => $thank_you_page,
            ]);
        } else {
            wp_send_json_error([
                'message' => 'Payment not completed. Status: ' . $paymentIntent->status
            ]);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Payment confirmation error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error confirming payment: ' . $e->getMessage()]);
    }

    wp_die();
}

add_action('wp_ajax_stripe_confirm_payment', 'stripe_confirm_payment_action');
add_action('wp_ajax_nopriv_stripe_confirm_payment', 'stripe_confirm_payment_action');