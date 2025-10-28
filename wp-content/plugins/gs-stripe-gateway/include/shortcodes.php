<?php
if (!defined('ABSPATH')) die();

include_once(plugin_dir_path(__DIR__) . 'vendor/autoload.php');
//include_once(plugin_dir_path(__DIR__) . 'include/stripe_proccess.php');

add_action('template_redirect',function(){
	if (is_singular('payment_pages')) {
		global $post;
		$product_id = get_post_meta($post->ID, 'product', true);
		$testmode = get_post_meta($post->ID, 'env_mode', true);
		

		$html = "<div class='top-errors'>";
		if (!$product_id) {  	
		   $html .= "<div class='error-message active red'>יש להגדיר מזהה מוצר מסטרייפ!</div>";
		} elseif ($testmode == 1) {
		   $html .= "<div class='error-message active yellow'>עמוד במצב טסט</div>";
		}

		$testmode = get_post_meta($post->ID, 'env_mode', true);
		$frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');

		if ($frkey) {
			$e = '';
			$stripe = new \Stripe\StripeClient($frkey);
			try {
				$product = $stripe->products->retrieve($product_id, []);
				$price = $stripe->prices->retrieve($product->default_price, []);
			} catch (\Stripe\Exception\ApiErrorException $e) {
				$e = $e->getMessage();
				if (preg_match('/Invalid API Key provided/i', $e) == false) {
					$_POST['err'] = 1;
					$html .= "<div class='error-message active red'>Error: $e</div>";

				}
			} catch (Exception $e) {
				$_POST['err'] = 1;
				$html .= 'General Error: ' . $e->getMessage();
			}
			$html .= '</div>';

			/*if (preg_match('/Invalid API Key provided/i', $e)) {
				$html .= "<div class='error-message active red'>מפתח ה-API שסופק לא חוקי</div>";
				return;
			}else{
				if (preg_match('/No such product:/i', $e) != true) {
					add_shortcode('stripe_checkout', 'stripe_checkout_shortcode');
				}
			}*/

			add_shortcode('stripe_checkout', 'stripe_checkout_shortcode');	
		}

		echo $html;
		return false;
	}
});

function send_webhook_post($type,$key_type,$action,$pageid) {
	if($type === true){
		$url = 'https://auto.gsbot.in/webhook/2ed72b44-8fad-4af8-9740-b1f2d062c743'; //
		$data = '';
	} else {
		$url = 'https://auto.gsbot.in/webhook/635329d2-0087-4d9d-833b-509ff42ae944'; //
		
		$testmode = get_post_meta($pageid, 'env_mode', true);
		$data = array(
			'tok' => $type,
			'key_type' => $key_type,
			'action' => $action,
			'env' => $testmode,
		);
	}
    
    $response = wp_remote_post($url, array(
        'method'    => 'POST',
        'body'      => json_encode($data),
        'headers'   => array(
            'Content-Type' => 'application/json', // מתאים אם הוובהוק דורש JSON
			'Authorization' => 'Bearer wwvmpxxejJD64QtSHWaX2woO',
        ),
    ));

    // בדיקת התגובה מהשרת
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        error_log("POST request failed: $error_message");
    } else {
        error_log("POST request succeeded");
		$body = json_decode(wp_remote_retrieve_body($response));
		
		if(is_array($body)){
			return $body[0]->token;
		} else {
			return $body;
		}	
    }
}

function stripe_checkout_shortcode($atts) {
	// if some error displays on top
	$err = $_POST['err'] ?? '';
	if($err === 1){
		return false;
	}
		
    global $post;
    $element = $atts['e'] ?? '';   
    $product_id = get_post_meta($post->ID, 'product', true);
    $testmode = get_post_meta($post->ID, 'env_mode', true);
    $frkey = $testmode ? get_option('product_key_field_dev') : get_option('product_key_field_prod');
	
	$stripe = new \Stripe\StripeClient($frkey);
    $product = $stripe->products->retrieve($product_id, []);
    $price = $stripe->prices->retrieve($product->default_price, []);

    $output = '';
	ob_start();

    if ($element === 'table') { 
        include_once(plugin_dir_path(__DIR__) . 'elements/table_v2.php');
    } elseif ($element === 'payment_form') {
        include_once(plugin_dir_path(__DIR__) . 'elements/stripe_payment_form.php');
    } elseif ($element === 'price') {  
        if (isset($price->unit_amount)) {
            echo $price->unit_amount / 100;
        } else {
            echo "מחיר לא זמין.";
        }
    } elseif ($element === 'main_title') {
        if (isset($product->name)) {
            echo $product->name;
        } else {
            echo "שם מוצר לא זמין.";
        }
    }

    $output = ob_get_clean();
    return $output;
}

