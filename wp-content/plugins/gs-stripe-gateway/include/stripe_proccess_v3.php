<?php

function create_subscription($args) {
    try {
        // יצירת מנוי
        $subscription = \Stripe\Subscription::create([
            'customer' => $args['customer_id'],
            'items' => [['price' => $args['price_id']]],
            'payment_behavior' => 'default_incomplete', // מבטיח שהמנוי יתחיל רק לאחר תשלום מוצלח
            'expand' => ['latest_invoice.payment_intent'],
            'metadata' => [
                'support_agent' => $args['support_agent'],
                'transaction_id' => base64_encode(date("Y-m-d H:i:s")),
            ],
        ]);

        $paymentIntent = $subscription->latest_invoice->payment_intent;

        // טיפול במצב של requires_action
        if ($paymentIntent->status === 'requires_action') {
            wp_send_json_success([
                'requires_action' => true,
                'payment_intent_client_secret' => $paymentIntent->client_secret,
                'message' => 'Subscription requires additional authentication.',
            ]);
        } else {
            wp_send_json_success([
                'message' => 'Subscription created successfully.',
                'subscription_id' => $subscription->id,
            ]);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Subscription creation error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error creating subscription: ' . $e->getMessage()]);
    }
}

function create_stripe_customer($args){

    $now = base64_encode(date("Y-m-d H:i:s"));
    $name               = $args['name'] ?? '';
    $lname              = $args['lname'] ?? '';
    $email              = $args['email'] ?? '';
    $phone              = $args['phone'] ?? '';
    $stripe             = $args['stripe'] ?? ''; 
    $page_id            = $args['page_id'] ?? '';
    $page_url           = get_permalink($page_id); // Get page URL
    $coupon_type        = $args['coupon_type'] ?? '';
    $coupon_code        = $args['coupon_code'] ?? ''; 
    $support_agent      = $args['support_agent'] ?? '';
    $seleced_price      = $args['price_id'] ?? '';
    $total_payments     = $args['total_payments'] ?? '';
    $payment_method_id  = $args['payment_method_id'] ?? '';
    
          
    // Search for existing customers by email or phone
    $customers = $stripe->customers->search([
        'query' => "email:'$email' OR phone:'$phone'",
    ]);
    
    // Check if any customer found
    if (count($customers->data) > 0) {
        // Customer exists, retrieve ID
        $existingCustomer = $customers->data[0];
        $customer_id = $existingCustomer->id;
    } else {	
        
        // No customer found, create a new one
        $customer = \Stripe\Customer::create([
            'email' => $email,
            'name' => "$name $lname",
            'phone' => $phone,
            'metadata' => [
                'support_agent' => $support_agent,
                'payment_page_url' => $page_url,
                'transaction_id' => $now,
                'price' => $seleced_price,
                'coupon_code'=>$coupon_code,
                'coupon_type'=>$coupon_type
            ],
        ]);
        $customer_id = $customer->id;
    }

    $args['customer_id'] = $customer_id;
    if ($total_payments >= 1) {
        return create_invoice($args);     
    } else{
        return create_subscription($args);
    }
    
}

function create_invoice($args) {

    try {
        // יצירת פריט חשבונית
        $invoiceItem = \Stripe\InvoiceItem::create([
            'customer' => $args['customer_id'],
            'price' => $args['price_id'],
            'quantity' => 1,
        ]);

        // יצירת חשבונית
        $invoice = \Stripe\Invoice::create([
            'customer' => $args['customer_id'],
            'auto_advance' => false, // מניעת סיום אוטומטי
        ]);

        // השגת אובייקט החשבונית
        $retrievedInvoice = \Stripe\Invoice::retrieve($invoice->id);

        // סיום החשבונית (finalizing)
        $finalizedInvoice = $retrievedInvoice->finalizeInvoice();
        
        if ($finalizedInvoice->status === 'open') {
            // אם החשבונית במצב "open", ביצוע תשלום
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $finalizedInvoice->amount_due,
                'currency' => $args['currency'],
                'customer' => $args['customer_id'],
                'payment_method' => $args['payment_method_id'],
                'confirm' => true,
                'setup_future_usage' => 'off_session',
                'return_url' => $args['thank_you_page'],
            ]);

        }    
        elseif ($finalizedInvoice->status === 'paid') {
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $finalizedInvoice->amount_due,
                'currency' => $args['currency'],
                'customer' => $args['customer_id'],
                'payment_method' => $args['payment_method_id'],
                'confirm' => true,
                'setup_future_usage' => 'off_session',
                'return_url' => $args['thank_you_page'],
            ]);

            //var_dump($finalizedInvoice->status);
            //wp_die();
        }  

            // טיפול במצב של requires_action
            if ($paymentIntent->status === 'requires_action') {
                wp_send_json_success([
                    'requires_action' => true,
                    'payment_intent_client_secret' => $paymentIntent->client_secret,
                    'message' => 'Invoice requires additional authentication.',
                ]);
            } elseif ($paymentIntent->status === 'succeeded') {
                wp_send_json_success([
                    'message' => 'Invoice paid successfully.',
                    'invoice_id' => $finalizedInvoice->id,
                ]);
            }

            


        else {
            wp_send_json_error(['message' => 'Invoice not open for payment.']);
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Invoice processing error: ' . $e->getMessage());
        wp_send_json_error(['message' => 'Error processing invoice: ' . $e->getMessage()]);
    }
}



function stripe_process_payment_action() {
    
    // בדוק אם מדובר בבקשת JSON
    if (strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        // קרא את גוף הבקשה
        $data = json_decode(file_get_contents('php://input'), true);
    } else {
        // השתמש בנתונים הרגילים מ-$_POST
        $data = $_POST;
    }

    // בדוק את ה-nonce
    if (!isset($data['nonce1']) || !wp_verify_nonce($data['nonce1'], 'stripe_payment_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }

    
    $page_id = isset($data['page_id']) ? intval($data['page_id']) : 0;
   

    if(!isset($data['page_id'])){
        wp_send_json_error(["message" => 'no page ID', "color" => "red"]);
        wp_die();
    }

    $product = sanitize_text_field(get_post_meta($page_id, 'product', true));
    if(!isset($product)) {
        wp_send_json_error(["message" => 'No Product ID Set on page', "color" => "red"]);
        wp_die();    
    }
    

    $total_payments = isset($data['payments']) ? sanitize_text_field($data['payments']) : '';
    $email =  isset($data['email']) ? sanitize_email($data['email']) : '';
    $name  = isset($data['firstName']) ? sanitize_text_field($data['firstName']) : '';
    $lname = isset($data['lastName']) ? sanitize_text_field($data['lastName']) : '';
    $phone = isset($data['phone']) ? sanitize_text_field($data['phone']) : '';
    $stripe_price_id   = isset($data['price_id']) ? sanitize_text_field($data['price_id']) : '';
    $support_agent     = isset($data['support_agent']) ? sanitize_text_field($data['support_agent']) : '';
    $thank_you_page    = esc_url(get_post_meta($page_id, 'thankyou', true));
    $coupon_code       = isset($data['coupon']) ? sanitize_text_field($data['coupon']) : '';
    $page_id           = isset($data['page_id']) ? sanitize_text_field($data['page_id']) : ''; 
    $payment_method_id = isset($data['payment_method_id']) ? sanitize_text_field($data['payment_method_id']) : '';
    $seleced_price     = isset($data['price_id']) ? sanitize_text_field($data['price_id']) : '';


    if(empty($total_payments) || empty($email) || empty($name) || empty($phone)){
        wp_send_json_error(["message" => 'mandatory fields missing', "color" => "red"]);
    }
    
    $is_local = false;
    
    
    $testmode = get_post_meta($page_id, 'testmode', true); 
	$frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');

    
    //\Stripe\Stripe::setApiKey($frkey);
    $stripe = new \Stripe\StripeClient($frkey);
    $product = $stripe->products->retrieve($product, []);
    
    if(!isset($product)) {
        wp_send_json_error(["message" => 'No Product ID', "color" => "red"]);
        wp_die();    
    }

    // Retrieve product and default price if available
    if(!empty($product)) {     
        $seleced_price = $stripe->prices->retrieve($seleced_price, []);
        $default_price = $product->default_price ?? '';
        $max_payments_limit = $product->metadata->payment_limit ?? '';    
    } 

    if(empty($thank_you_page)){
		$thank_you_page = home_url('/thank-you');
		$is_local = true;
	}

    // Check if Stripe price ID exists
    if(empty($stripe_price_id)) {
        wp_send_json_error(["message" => 'stripe price id not exist', "color" => "red"]);
        return;
    }

    // Input validation: Check required fields and payment limits
    if (empty($email) || empty($total_payments) || empty($page_id)) {
        $error_message = empty($total_payments) ? "לא נבחרו תשלומים" : "בעיה באחד הפרטים, יש לפנות למנהל האתר";
        wp_send_json_error(["message" => $error_message, "color" => "red"]);
        exit;
    } elseif ($total_payments > $max_payments_limit) {
        wp_send_json_error(["message" => "מקסימום $max_payments_limit תשלומים מורשים", "color" => "red"]);
        exit;
    }

    
    $amount = $seleced_price->unit_amount;
    $discount = false;
    $coupon_type = false;
    $after_coupon = set_code_coupon($coupon_code,$amount);
    
    $currency = $seleced_price->currency ?? '';
    if($after_coupon != false){
        $coupon_data = json_decode($after_coupon);
        $amount = $coupon_data->discounted_amount;
        
        if($coupon_data->discount_details->percent_off != 0){
            $coupon_type = 'percent_off';
            $discount    = $seleced_price->unit_amount - $coupon_data->discounted_amount ?? '';
        }
        else{
            $coupon_type = 'amount_off';
            $discount    = $seleced_price->unit_amount - $coupon_data->discounted_amount ?? '';
        }

    }
    
    if($seleced_price->active != true){
        wp_send_json_error(["message" => "המחיר לא פעיל בסטרייפ", "color" => "red"]);
        exit;
    }
    
    
    $args = array(
        'email'             =>$email,
        'name'              =>$name,
        'lname'             =>$lname,
        'phone'             =>$phone,
        'stripe'            =>$stripe,
        'support_agent'     =>$support_agent,
        'payment_method_id' =>$payment_method_id,
        'amount'            =>$amount,
        'currency'          =>$currency,
        'thank_you_page'    =>$thank_you_page,
        'page_id'           =>$page_id,
        'total_payments'    =>$total_payments,
        'coupon_code'       =>$coupon_code,
        'coupon_type'       =>$coupon_type,
        'discount'          =>$discount,
        'price_id'          =>$seleced_price->id ?? '',
    );

    //var_dump($seleced_price->unit_amount,$seleced_price->type, $seleced_price->active,$seleced_price->product,$discount);
    //var_dump($total_payments,$seleced_price->type);

    \Stripe\Stripe::setApiKey($frkey);

    // חיפוש לקוח קיים או יצירת לקוח חדש
    try {
        $customers = \Stripe\Customer::all(['email' => $email]);
        if (count($customers->data) > 0) {
            $customer_id = $customers->data[0]->id;
        } else {
            $customer = \Stripe\Customer::create([
                'email' => $email,
                'name' => "$name $lname",
                'phone' => $phone,
            ]);
            $customer_id = $customer->id;
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        wp_send_json_error(['message' => 'Error creating or retrieving customer: ' . $e->getMessage()]);
        return;
    }

    // צירוף אמצעי התשלום ללקוח
    // שימוש באובייקט של \Stripe\StripeClient
    $stripe = new \Stripe\StripeClient($frkey);

    // צירוף אמצעי התשלום ללקוח
    try {
        $stripe->paymentMethods->attach(
            $payment_method_id,
            ['customer' => $customer_id]
        );

        // עדכון אמצעי התשלום כברירת מחדל ללקוח
        $stripe->customers->update($customer_id, [
            'invoice_settings' => ['default_payment_method' => $payment_method_id],
        ]);
    } catch (\Stripe\Exception\ApiErrorException $e) {
        wp_send_json_error(['message' => 'Error attaching payment method: ' . $e->getMessage()]);
        return;
    }

    $total_payments = $args['total_payments'] ?? '';
    $args['customer_id'] = $customer_id;
        
    if ($total_payments == 1) {
         return create_invoice($args); // תשלום חד-פעמי
    } else {
         return create_subscription($args); 
    }
            

    wp_die();
}
add_action('wp_ajax_stripe_process_payment', 'stripe_process_payment_action');
add_action('wp_ajax_nopriv_stripe_process_payment', 'stripe_process_payment_action');