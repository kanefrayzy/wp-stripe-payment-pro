<?php



function check_promo_code($code) {
    try {
        $promo_code = \Stripe\PromotionCode::all(['code' => $code, 'limit' => 1]);
        error_log('Promotion Code Response: ' . print_r($promo_code, true)); // הוסף לוג

        if (!empty($promo_code->data) && $promo_code->data[0]->active) {
            $coupon = $promo_code->data[0]->coupon;
            return [
                'status' => 'success',
                'percent_off' => $coupon->percent_off ?? 0,
                'amount_off' => $coupon->amount_off ?? 0
            ];
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Promo code error: ' . $e->getMessage());
    }
    return ['status' => 'error'];
}

function check_coupon_code($code) {
    try {
        $coupon = \Stripe\Coupon::retrieve($code);
        error_log('Coupon Response: ' . print_r($coupon, true)); // הוסף לוג

        if ($coupon->valid) {
            return [
                'status' => 'success',
                'percent_off' => $coupon->percent_off ?? 0,
                'amount_off' => $coupon->amount_off ?? 0
            ];
        }
    } catch (\Stripe\Exception\ApiErrorException $e) {
        error_log('Coupon code error: ' . $e->getMessage());
    }
    return ['status' => 'error'];
}

function respond_with_discount($details, $original_amount, $coupon_code,$request) {
    $discounted_amount = $original_amount;

    if ($details['percent_off'] > 0) {
        $discounted_amount -= ($original_amount * $details['percent_off'] / 100);
    } elseif ($details['amount_off'] > 0) {
        $discounted_amount -= $details['amount_off'];
    }

    if($request == 'set_code_coupon'){
        $coupon_data = json_encode(
            array(
                'discounted_amount'=>$discounted_amount,
                'discount_details'=>$details
            )
        );  
        return $coupon_data;
    }
    else{
        wp_send_json_success([
            'status' => 'success',
            'coupon_code'       => $coupon_code,
            'original_amount'   => $original_amount,
            'discounted_amount' => $discounted_amount,
            'discount_details'  => $details,
        ]);
    }

    wp_die();
}

function set_code_coupon($coupon_code,$amount) {

    include_once(plugin_dir_path(__DIR__) . 'vendor/autoload.php');  
    $request = 'set_code_coupon';
    // הגדרת מפתח API של Stripe
    $testmode = get_post_meta(61, 'testmode', true);
    $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
    \Stripe\Stripe::setApiKey($frkey);

    $params['amount'] = $amount;

    if (!empty($coupon_code)) {
        $coupon_code = $coupon_code;
        
        // בדיקת קוד מבצע
        $promo = check_promo_code($coupon_code);     
        if ($promo['status'] === 'success') {
            return respond_with_discount($promo, $params['amount'], $coupon_code,$request);
        }

        // בדיקת קוד קופון
        $coupon = check_coupon_code($coupon_code);
        if ($coupon['status'] === 'success') {
            return respond_with_discount($coupon, $params['amount'], $coupon_code,$request);
        }
        
        // אם אף קוד לא נמצא
        echo json_encode(['status' => 'error', 'message' => 'ניראה שקוד קופון או פרומו קוד לא קיימים או שייכים לסביבה אחרת']);
    } else {
        return false;
    }

    wp_die();
}


function check_code_coupon() {

    $data = $_POST;

    // בדיקה והוספת לוגים לקלטים
    error_log('Received Data: ' . print_r($data, true));

    if (!isset($data['nonce2']) || !wp_verify_nonce($data['nonce2'], 'stripe_coupon_nonce')) {
        wp_send_json_error(['message' => 'Invalid nonce']);
        wp_die();
    }

    if (empty($data['coupon_code'])) {
        wp_send_json_error(['status' => 'error', 'message' => 'No coupon code provided']);
        return;
    }

    include_once(plugin_dir_path(__DIR__) . 'vendor/autoload.php');  
    $testmode = get_post_meta($data['page_id'], 'testmode', true);
    $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
    \Stripe\Stripe::setApiKey($frkey);

    $coupon_code = $data['coupon_code'];
    $price_id = $data['price'];

    error_log('Coupon Code: ' . $coupon_code);
    error_log('Price ID: ' . $price_id);

    try {
        $stripe = new \Stripe\StripeClient($frkey);
        $price = $stripe->prices->retrieve($price_id, []);
        $amount = $price['unit_amount'] ?? '';
        error_log('Price Retrieved: ' . print_r($price, true));
    } catch (\Exception $e) {
        error_log('Price Retrieve Error: ' . $e->getMessage());
        wp_send_json_error(['status' => 'error', 'message' => 'Invalid price ID']);
        return;
    }

    // המשך תהליך בדיקת הקופונים
    $promo = check_promo_code($coupon_code);
    if ($promo['status'] === 'success') {
        respond_with_discount($promo, $amount, $coupon_code, false);
        return;
    }

    $coupon = check_coupon_code($coupon_code);
    if ($coupon['status'] === 'success') {
        respond_with_discount($coupon, $amount, $coupon_code, false);
        return;
    }

    wp_send_json_error(['status' => 'error', 'message' => 'ניראה שקוד קופון או פרומו קוד לא קיימים או שייכים לסביבה אחרת']);
    wp_die();
}

add_action('wp_ajax_check_code_coupon', 'check_code_coupon');
add_action('wp_ajax_nopriv_check_code_coupon', 'check_code_coupon');